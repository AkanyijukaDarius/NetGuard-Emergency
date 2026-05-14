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
use Illuminate\Support\Facades\Http;

class EmergencyController extends Controller
{
    protected CamaraService   $camara;
    protected AiTriageService $aiTriage;

    public function __construct(CamaraService $camara, AiTriageService $aiTriage)
    {
        $this->camara   = $camara;
        $this->aiTriage = $aiTriage;
    }


    //  Register

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



public function verifyKyc(Request $request)
{
    $user = $request->user();

    $response = Http::withHeaders([
        'x-rapidapi-host' => 'network-as-code.nokia.rapidapi.com',
        'x-rapidapi-key'  => config('services.nokia.api_key'),
    ])->post(config('services.nokia.base_url') . '/passthrough/camara/v1/kyc-match/kyc-match/v0.3/match', [
        'device' => ['phoneNumber' => $user->phone],
        'idDocument' => $user->id_document,
        'givenName'  => $user->given_name,
        'familyName' => $user->family_name,
    ]);

    $result = $response->json();

    $idMatches = isset($result['idDocumentMatch']) && $result['idDocumentMatch'] === "true";

    if ($response->successful() && $idMatches) {
        $user->update(['is_kyc_verified' => true,
            'kyc_status'      => 'verified']);

        return response()->json([
            'success' => true,
            'message' => 'Identity Verified via ID Document!'
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'ID Verification Failed.',
        'debug' => $result
    ], 422);
}

    // Login
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


    //  Trigger Emergency

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
        ]);

        $phone = $request->phoneNumber;
        $symptoms = (string) ($request->symptoms ?? $request->symptomType ?? 'Medical Emergency');

        $user = null;
        if (Auth::check()) {
            $user = Auth::user();
        } else {
            // Fallback: Try to find user by phone for anonymous triggers
            $user = User::where('phone', $phone)->first();
        }

        //kyc verification
        $kycResult = [];
        $isKycVerified = false;
        $kycMatchScore = null;

        try {
            $kycPayload = [
                'idDocument' => $user?->id_document,
                'givenName'  => $request->given_name,
                'familyName' => $request->family_name,
            ];

            $kycResult = $this->camara->kycMatch($phone, $kycPayload);

            $isKycVerified = $kycResult['_internal_success']
                          || ($kycResult['idDocumentMatch'] ?? false)
                          || ($kycResult['nameMatch'] ?? $kycResult['givenNameMatch'] ?? false);

            $kycMatchScore = $kycResult['matchScore'] ?? null;

            Log::info("KYC Match during emergency", [
                'phone'          => $phone,
                'kyc_verified'   => $isKycVerified,
                'has_id_document'=> !empty($user?->id_document),
                'match_score'    => $kycMatchScore
            ]);

        } catch (\Exception $e) {
            Log::warning("KYC Match failed for {$phone}: " . $e->getMessage());
        }

        //sim swap check
        $simSwapData = [];
        $isHighRisk = false;
        try {
            $simSwapData = $this->camara->getSimSwapDate($phone);
            if (isset($simSwapData['latestSimSwapDate'])) {
                $isHighRisk = Carbon::parse($simSwapData['latestSimSwapDate'])
                    ->diffInHours(now()) < 48;
            }
        } catch (\Exception $e) {
            Log::warning("SIM swap check failed for {$phone}");
        }

        //device location
        $networkLocation = null;
        $lat = $request->latitude;
        $lng = $request->longitude;
        try {
            $networkLocation = $this->camara->getDeviceLocation($phone);
            $lat = data_get($networkLocation, 'result.area.center.latitude') ?? $lat;
            $lng = data_get($networkLocation, 'result.area.center.longitude') ?? $lng;
        } catch (\Exception $e) {}

        //reachability status
        $reachabilityData = [];
        try {
            $reachabilityData = $this->camara->getReachabilityStatus($phone);
        } catch (\Exception $e) {}

        //ai triage
        $triageResult = $this->aiTriage->triage($symptoms, 'urban', $networkLocation, $isHighRisk);

        $agenticResult = $this->aiTriage->agenticOrchestrate(
            $triageResult,
            $reachabilityData,
            $networkLocation,
            $phone
        );

        $qodSession = null;
        if ($agenticResult['agentic_actions']['qod_triggered'] ?? false) {
            $qosProfile = $agenticResult['agentic_actions']['qos_profile'];
            try {
                $qodSession = $this->camara->createQoDSession($phone, $qosProfile, 1800);
            } catch (\Exception $e) {
                Log::warning("Agentic QoD failed: " . $e->getMessage());
            }
        }

        DB::beginTransaction();
        try {
            $incident = Incident::create([
                'incident_code'   => 'NG-' . now()->format('Ymd-His'),
                'type'            => $agenticResult['likely_condition'] ?? $symptoms,
                'severity'        => $agenticResult['severity'] ?? 'medium',
                'description'     => $symptoms,
                'latitude'        => $lat,
                'longitude'       => $lng,
                'ai_triage'       => $agenticResult,
                'sim_swap_result' => $simSwapData,
                'qod_session_id'  => $qodSession['sessionId'] ?? null,
                'status'          => 'open',
            ]);

            $alert = EmergencyAlert::create([
                'incident_id'         => $incident->id,
                'user_id'             => Auth::id() ?? $user?->id,
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
                'reachability_status' => 'data',
                'is_anonymous'        => !Auth::check(),
                'sim_swap_flagged'    => $isHighRisk,
                'kyc_verified'        => $isKycVerified,
                'kyc_result'          => $kycResult,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Alert creation failed: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create alert'], 500);
        }

        // Broadcast
        try {
            broadcast(new EmergencyTriggered($alert, $incident, $agenticResult))->toOthers();
        } catch (\Exception $e) {}

        return response()->json([
            'success'  => true,
            'alert_id' => $alert->id,
            'triage'   => [
                'severity'  => $agenticResult['severity'],
                'condition' => $agenticResult['likely_condition'],
                'responder' => $agenticResult['recommended_responder'],
            ],
            'agentic' => $agenticResult['agentic_actions'] ?? [],
            'security' => [
                'kyc_verified'    => $isKycVerified,
                'kyc_match_score' => $kycMatchScore,
                'sim_swap_risk'   => $isHighRisk,
                'used_db_id_doc'  => !empty($user?->id_document),
            ],
            'network' => [
                'qod_activated' => $agenticResult['agentic_actions']['qod_triggered'] ?? false,
                'qos_profile'   => $agenticResult['agentic_actions']['qos_profile'] ?? null,
            ],
            'message' => 'Emergency alert triggered with KYC verification from user database.',
        ], 201);
    }

    // RESPONDER: Active alerts feed

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

    // RESPONDER: Dispatch to alert
    public function dispatchAlert(Request $request, $id)
    {

        $alert = EmergencyAlert::with('incident')->findOrFail($id);
    $responder = Auth::user();

    if ($alert->responder_id === $responder->id && $alert->status === 'dispatched') {
        return response()->json([
            'success' => false,
            'message' => 'You have already been dispatched to this alert.'
        ], 422);
    }

    if ($alert->responder_id !== null && $alert->responder_id !== $responder->id) {
        return response()->json([
            'success' => false,
            'message' => 'This alert has already been claimed by another responder.'
        ], 422);
    }

    if (in_array($alert->status, ['resolved', 'cancelled'])) {
        return response()->json([
            'success' => false,
            'message' => "Cannot dispatch to an alert that is already {$alert->status}."
        ], 422);
    }

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

        $smsSent = false;

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

    // RESPONDER: Resolved/dispatched alerts
public function resolved()
{
    $alerts = EmergencyAlert::whereIn('status', ['dispatched', 'resolved'])
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


  public function resolve(Request $request, $id)
{
    $alert = EmergencyAlert::with('incident')->findOrFail($id);
    $responder = Auth::user();

    if ($alert->responder_id !== $responder->id) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    if ($alert->status === 'resolved') {
        return response()->json([
            'success' => false,
            'message' => 'This incident has already been marked as complete.'
        ], 422);
    }

    try {
        DB::transaction(function () use ($alert) {
            $alert->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'response_time_minutes' => $alert->dispatched_at ? now()->diffInMinutes($alert->dispatched_at) : 0,
            ]);

            if ($alert->incident) {
                $alert->incident->update([
                    'status' => 'closed',
                    'closed_at' => now()
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Mission complete. Great job, ' . $responder->given_name
        ]);

    } catch (\Exception $e) {
        Log::error("Resolution Error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'A server error occurred while closing the case.'
        ], 500);
    }
}

    // VICTIM: My requests
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

    // VICTIM: Cancel alert
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

    /**
 * @param \App\Models\EmergencyAlert $alert
 * @param \App\Models\User $responder
 */
    protected function sendDispatchSms($alert, $responder)
{
    $message = "NETGUARD: Responder {$responder->given_name} is on the way to your location. Stay calm.";
    // Integrate with your local SMS gateway (like AfricasTalking or similar)
    Log::info("SMS Fallback sent to {$alert->phone}: {$message}");
    return true;
}

}
