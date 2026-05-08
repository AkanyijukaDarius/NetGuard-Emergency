<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\EmergencyAlert;
use App\Models\User;
use App\Services\CamaraService;
use App\Services\AiTriageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Events\EmergencyTriggered;
use App\Events\EmergencyCancelled;
use App\Events\ResponderDispatched;
use Carbon\Carbon;

class EmergencyController extends Controller
{
    protected CamaraService   $camara;
    protected AiTriageService $aiTriage;

    public function __construct(CamaraService $camara, AiTriageService $aiTriage)
    {
        $this->camara   = $camara;
        $this->aiTriage = $aiTriage;
    }

    // ─────────────────────────────────────────────────────────
    // AUTH: Register
    // ─────────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $validated = $request->validate([
            'phone'          => 'required|string|unique:users,phone',
            'given_name'     => 'required|string',
            'family_name'    => 'required|string',
            'id_document'    => 'nullable|string',
            'role'           => 'required|string|in:user,responder',
            'responder_code' => 'required_if:role,responder|nullable|string',
        ]);

        if ($validated['role'] === 'responder' && $validated['responder_code'] !== 'NET-2026') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid responder authorization code.',
            ], 403);
        }

        $user = User::create([
            'phone'       => $validated['phone'],
            'given_name'  => $validated['given_name'],
            'family_name' => $validated['family_name'],
            'id_document' => $validated['id_document'] ?? null,
            'role'        => $validated['role'],
            'name'        => $validated['given_name'] . ' ' . $validated['family_name'],
            'email'       => $validated['phone'] . '@netguard.com',
            'password'    => Hash::make($validated['phone']),
        ]);

        return response()->json([
            'success' => true,
            'token'   => $user->createToken('netguard_token')->plainTextToken,
            'user'    => $user,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // AUTH: Login
    // ─────────────────────────────────────────────────────────
    public function login(Request $request)
    {
        // Allow phone-only login (password defaults to phone number)
        if (!$request->has('password') && $request->has('phone')) {
            $request->merge(['password' => $request->phone]);
        }

        $validated = $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $validated['phone'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        try {
            if (!Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }
        } catch (\RuntimeException $e) {
            // Plain-text password stored (legacy) — rehash and save
            if ($validated['password'] === $user->password) {
                $user->password = Hash::make($validated['password']);
                $user->save();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }
        }

        return response()->json([
            'success' => true,
            'token'   => $user->createToken('netguard_token')->plainTextToken,
            'user'    => $user,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // MAIN: Trigger Emergency
    // ─────────────────────────────────────────────────────────
    public function trigger(Request $request)
    {
        $request->validate([
            'phoneNumber' => 'required|string',
            'givenName'   => 'required|string',
            'familyName'  => 'required|string',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'symptoms'    => 'nullable|string',
            'symptomType' => 'nullable|string',
            'device_ip'   => 'nullable|string',
        ]);

        $phone = $request->phoneNumber;

        // ✅ Resolve symptoms once — never pass null to triage
        $symptoms = (string) ($request->symptoms
                  ?? $request->symptomType
                  ?? 'Medical Emergency');

        // ── SIM Swap check ────────────────────────────────────
        $simSwapData = [];
        $isHighRisk  = false;
        try {
            $simSwapData = $this->camara->getSimSwapDate($phone);
            if (isset($simSwapData['latestSimSwapDate'])) {
                $isHighRisk = Carbon::parse($simSwapData['latestSimSwapDate'])
                    ->diffInHours(now()) < 48;
            }
        } catch (\Exception $e) {
            Log::warning("SIM swap check failed for {$phone}: " . $e->getMessage());
        }

        // ── CAMARA Location ───────────────────────────────────
        $networkLocation = null;
        $lat = $request->latitude;
        $lng = $request->longitude;
        try {
            $networkLocation = $this->camara->getDeviceLocation($phone);
            $lat = data_get($networkLocation, 'result.area.center.latitude')
                ?? data_get($networkLocation, 'area.center.latitude')
                ?? $lat;
            $lng = data_get($networkLocation, 'result.area.center.longitude')
                ?? data_get($networkLocation, 'area.center.longitude')
                ?? $lng;
        } catch (\Exception $e) {
            Log::warning("Location retrieval failed for {$phone}: " . $e->getMessage());
        }

        // ── Reachability ──────────────────────────────────────
        $isReachable  = true;
        $hasData      = true;
        $connectivity = [];
        try {
            $reachabilityData = $this->camara->getReachabilityStatus($phone);
            $isReachable  = data_get($reachabilityData, 'reachable', true);
            $connectivity = data_get($reachabilityData, 'result.connectivity', []);
            $hasData      = in_array('DATA', $connectivity) || $isReachable;
        } catch (\Exception $e) {
            Log::warning("Reachability check failed for {$phone}: " . $e->getMessage());
        }

        // ── QoD Session ───────────────────────────────────────
        $qodSession = null;
        try {
            $qodSession = $this->camara->createQoDSession(
                $phone,
                'DOWNLINK_M_UPLINK_L',
                600,
                $request->input('device_ip', '233.252.0.1')
            );
        } catch (\Exception $e) {
            Log::warning("QoD session failed for {$phone}: " . $e->getMessage());
        }

        // ── AI Triage ─────────────────────────────────────────
        $triageResult = [];
        try {
            $triageResult = $this->aiTriage->triage(
                $symptoms,
                'urban',
                $networkLocation,
                $isHighRisk
            );
        } catch (\Exception $e) {
            Log::warning("AI triage failed: " . $e->getMessage());
            $triageResult = [
                'severity'                    => 'medium',
                'likely_condition'            => $symptoms,
                'recommended_responder'       => 'VHT',
                'first_aid_tips'              => [
                    'Keep patient calm',
                    'Monitor breathing',
                    'Do not move unless necessary',
                ],
                'estimated_response_priority' => 'urgent',
                'reasoning'                   => 'Fallback triage — AI unavailable',
            ];
        }

        // ── DB writes ─────────────────────────────────────────
        DB::beginTransaction();
        try {
            $incident = Incident::create([
                'incident_code'   => 'NG-' . now()->format('Ymd-His'),
                'type'            => $triageResult['likely_condition'] ?? $symptoms,
                'severity'        => $triageResult['severity']         ?? 'medium',
                'description'     => $symptoms,
                'latitude'        => $lat,
                'longitude'       => $lng,
                'ai_triage'       => $triageResult,
                'sim_swap_result' => $simSwapData,
                'qod_session_id'  => $qodSession['sessionId'] ?? null,
                'status'          => 'open',
            ]);

            $alert = EmergencyAlert::create([
                'incident_id'         => $incident->id,
                'user_id'             => Auth::id(),
                'phone'               => $phone,
                'given_name'          => $request->givenName,
                'family_name'         => $request->familyName,
                'latitude'            => $lat,
                'longitude'           => $lng,
                'network_location'    => $networkLocation,
                'symptoms'            => $symptoms,
                'status'              => 'pending',
                'session_token'       => Str::uuid(),
                'qod_session_id'      => $qodSession['sessionId'] ?? null,
                'reachability_status' => ($isReachable && $hasData) ? 'data' : 'sms',
                'connectivity_type'   => $connectivity[0] ?? 'OFFLINE',
                'is_anonymous'        => !Auth::check(),
                'sim_swap_flagged'    => $isHighRisk,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Alert creation failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create alert: ' . $e->getMessage(),
            ], 500);
        }

        // ── Broadcast ─────────────────────────────────────────
        try {
            broadcast(new EmergencyTriggered($alert, $incident, $triageResult))->toOthers();
        } catch (\Exception $e) {
            Log::error("Broadcast failed: " . $e->getMessage());
        }

        return response()->json([
            'success'  => true,
            'alert_id' => $alert->id,
            'alert'    => $alert->load('incident'),
            'triage'   => [
                'severity'  => $triageResult['severity'],
                'condition' => $triageResult['likely_condition'],
                'responder' => $triageResult['recommended_responder'],
                'tips'      => $triageResult['first_aid_tips'] ?? [],
                'priority'  => $triageResult['estimated_response_priority'] ?? 'urgent',
            ],
            'network' => [
                'location_source' => $networkLocation ? 'camara' : 'gps',
                'reachable'       => $isReachable,
                'sms_fallback'    => !$hasData,
                'qod_active'      => !is_null($qodSession),
            ],
            'security' => [
                'sim_swap_risk' => $isHighRisk,
            ],
            'message' => 'Emergency alert sent!',
        ], 201);
    }

    // ─────────────────────────────────────────────────────────
    // RESPONDER: Active alerts feed
    // ─────────────────────────────────────────────────────────
    public function active()
    {
        $responder = Auth::user();

        // Cache responder location — avoid CAMARA call on every feed refresh
        $cacheKey = "responder_loc_{$responder->phone}";
        $rLoc = Cache::remember($cacheKey, 120, function () use ($responder) {
            try {
                return $this->camara->getDeviceLocation($responder->phone);
            } catch (\Exception $e) {
                Log::warning("Responder location failed: " . $e->getMessage());
                return null;
            }
        });

        $rLat = data_get($rLoc, 'result.area.center.latitude')
             ?? data_get($rLoc, 'area.center.latitude');
        $rLng = data_get($rLoc, 'result.area.center.longitude')
             ?? data_get($rLoc, 'area.center.longitude');

        $alerts = EmergencyAlert::whereIn('status', ['pending', 'dispatched', 'on_way'])
            ->with(['incident', 'responder'])
            ->latest()
            ->get()
            ->map(function ($alert) use ($rLat, $rLng) {
                $vLoc = $alert->network_location;

                return array_merge($alert->toArray(), [
                    'incident'          => $alert->incident,
                    'responder'         => $alert->responder,
                    'responder_api_lat' => $rLat,
                    'responder_api_lng' => $rLng,
                    'victim_lat'        => data_get($vLoc, 'result.area.center.latitude')
                                       ?? data_get($vLoc, 'area.center.latitude')
                                       ?? $alert->latitude,
                    'victim_lng'        => data_get($vLoc, 'result.area.center.longitude')
                                       ?? data_get($vLoc, 'area.center.longitude')
                                       ?? $alert->longitude,
                    'accuracy_radius'   => data_get($vLoc, 'result.area.radius')
                                       ?? data_get($vLoc, 'area.radius')
                                       ?? 15,
                ]);
            });

        return response()->json(['success' => true, 'data' => $alerts]);
    }

    // ─────────────────────────────────────────────────────────
    // RESPONDER: Dispatch to alert
    // ─────────────────────────────────────────────────────────
    public function dispatchAlert(Request $request, $id)
    {
        $alert     = EmergencyAlert::with('incident')->findOrFail($id);
        $responder = Auth::user();
        $smsSent   = false;

        $alert->update([
            'status'        => 'dispatched',
            'responder_id'  => $responder->id,
            'dispatched_at' => now(),
        ]);

        // Clean up QoD session now that alert is dispatched
        if ($alert->qod_session_id) {
            try {
                $this->camara->deleteQoDSession($alert->qod_session_id);
            } catch (\Exception $e) {
                Log::warning("QoD session cleanup failed: " . $e->getMessage());
            }
        }

        // SMS fallback if victim has no data connection
        if ($alert->reachability_status === 'sms') {
            $smsSent = $this->sendDispatchSms($alert, $responder);
        }

        // Broadcast to victim's app (data connection)
        try {
            broadcast(new ResponderDispatched($alert, $responder))->toOthers();
        } catch (\Exception $e) {
            Log::error("Dispatch broadcast failed: " . $e->getMessage());
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Victim notified.',
            'responder'     => $responder->given_name,
            'sms_sent'      => $smsSent,
            'victim_phone'  => $alert->phone,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // RESPONDER: Resolved/dispatched alerts
    // ─────────────────────────────────────────────────────────
    public function resolved()
    {
        $alerts = EmergencyAlert::where('status', 'dispatched')
            ->where('responder_id', Auth::id())
            ->with(['incident'])
            ->latest('updated_at')
            ->get()
            ->map(function ($alert) {
                $vLoc = $alert->network_location;

                return array_merge($alert->toArray(), [
                    'incident'        => $alert->incident,
                    'victim_lat'      => data_get($vLoc, 'result.area.center.latitude')
                                      ?? data_get($vLoc, 'area.center.latitude')
                                      ?? $alert->latitude,
                    'victim_lng'      => data_get($vLoc, 'result.area.center.longitude')
                                      ?? data_get($vLoc, 'area.center.longitude')
                                      ?? $alert->longitude,
                    'accuracy_radius' => data_get($vLoc, 'result.area.radius')
                                      ?? data_get($vLoc, 'area.radius')
                                      ?? 15,
                ]);
            });

        return response()->json(['success' => true, 'data' => $alerts]);
    }

    // ─────────────────────────────────────────────────────────
    // VICTIM: My requests
    // ─────────────────────────────────────────────────────────
    public function getMyRequests()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $requests = EmergencyAlert::with(['incident', 'responder'])
            ->where('phone', $user->phone)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    // ─────────────────────────────────────────────────────────
    // VICTIM: Cancel alert
    // ─────────────────────────────────────────────────────────
    public function cancelEmergency(Request $request, $id)
    {
        $existsAtAll = EmergencyAlert::find($id);

        if (!$existsAtAll) {
            return response()->json([
                'success' => false,
                'message' => "Alert #{$id} does not exist.",
            ], 404);
        }

        $alert = EmergencyAlert::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$alert) {
            return response()->json([
                'success'            => false,
                'message'            => 'You do not own this alert.',
                'debug_alert_owner'  => $existsAtAll->user_id,
                'debug_current_user' => Auth::id(),
            ], 403);
        }

        if (in_array($alert->status, ['resolved', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Alert is already {$alert->status}.",
            ], 400);
        }

        // Clean up QoD session if active
        if ($alert->qod_session_id) {
            try {
                $this->camara->deleteQoDSession($alert->qod_session_id);
            } catch (\Exception $e) {
                Log::warning("QoD cleanup on cancel failed: " . $e->getMessage());
            }
        }

        $alert->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
        ]);

        try {
            broadcast(new EmergencyCancelled($alert, 'victim'))->toOthers();
        } catch (\Exception $e) {
            Log::warning("Cancellation broadcast failed: " . $e->getMessage());
        }

        return response()->json([
            'success'  => true,
            'message'  => 'Emergency cancelled successfully.',
            'alert_id' => $alert->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: SMS dispatch notification
    // ─────────────────────────────────────────────────────────
    // private function sendDispatchSms(EmergencyAlert $alert, User $responder): bool
    // {
    //     try {
    //         $AT      = new \AfricasTalking\SDK\AfricasTalking(
    //             config('services.africastalking.username'),
    //             config('services.africastalking.api_key')
    //         );
    //         $sms     = $AT->sms();
    //         $incType = $alert->incident?->type ?? 'Emergency';

    //         $message = "NETGUARD EMERGENCY\n"
    //                  . "Your {$incType} alert has been received.\n"
    //                  . "Responder {$responder->given_name} is on the way.\n"
    //                  . "Ref: #{$alert->id}\n"
    //                  . "Stay calm. Help is coming.";

    //         $result = $sms->send([
    //             'to'      => $alert->phone,
    //             'message' => $message,
    //             'from'    => config('services.africastalking.sender_id', 'NetGuard'),
    //         ]);

    //         Log::info("SMS sent to {$alert->phone}", ['result' => $result]);
    //         return true;

    //     } catch (\Exception $e) {
    //         Log::error("SMS failed for alert #{$alert->id}: " . $e->getMessage());
    //         return false;
    //     }
    // }
}
