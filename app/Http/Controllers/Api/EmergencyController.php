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
use App\Events\EmergencyTriggered;
use App\Events\EmergencyCancelled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EmergencyController extends Controller
{
    protected $camara;
    protected $aiTriage;

    public function __construct(CamaraService $camara, AiTriageService $aiTriage)
    {
        $this->camara = $camara;
        $this->aiTriage = $aiTriage;
    }

    /**
     * Main Endpoint: Trigger Emergency Alert (Anonymous + Network Intelligence)
     */
    /**
 * Main Endpoint: Trigger Emergency Alert (Anonymous + Network Intelligence)
 */

// Update your Register Method
public function register(Request $request)
{
    $validated = $request->validate([
        'phone' => 'required|string|unique:users,phone',
        'given_name' => 'required|string',
        'family_name' => 'required|string',
        'id_document' => 'nullable|string',
        'role' => 'required|string|in:user,responder',
        'responder_code' => 'required_if:role,responder|nullable|string',
    ]);

    if ($validated['role'] === 'responder' && $validated['responder_code'] !== 'NET-2026') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid authorization code for emergency responders.'
            ], 403);
        }

    $user = User::create([
        'phone' => $validated['phone'],
        'given_name' => $validated['given_name'],
        'family_name' => $validated['family_name'],
        'id_document' => $validated['id_document'],
        'role' => $validated['role'],
        'name' => $validated['given_name'] . ' ' . $validated['family_name'],
        'email' => $validated['phone'] . '@netguardemergency.com',
        'password' => Hash::make($validated['phone']),
    ]);

    // Generate token
    $token = $user->createToken('netguard_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'token' => $token,
        'user' => [
            'phone' => $user->phone,
            'given_name' => $user->given_name,
            'family_name' => $user->family_name,
            'role' => $user->role,
        ]
    ], 201);
}


public function login(Request $request)
{
    $request->validate(['phone' => 'required|string']);

    $user = \App\Models\User::where('phone', $request->phone)->first();

    if ($user) {
        $token = $user->createToken('netguard_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'phone' => $user->phone,
                'given_name' => $user->given_name,
                'family_name' => $user->family_name,
                'role' => $user->role,
                'is_kyc_verified' => (bool) $user->is_kyc_verified,
                'id_document' => $user->id_document,
            ]
        ]);
    }

    return response()->json(['success' => false, 'message' => 'Not registered.'], 404);
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
        $user->update(['is_kyc_verified' => 1]); //

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

public function trigger(Request $request)
{
    // 1. Match the Nokia/CAMARA naming convention in validation
    $request->validate([
        'phoneNumber' => 'required|string|max:15', // Changed from 'phone'
        'givenName'   => 'required|string',        // Changed from 'given_name'
        'familyName'  => 'required|string',       // Changed from 'family_name'
        'idDocument'  => 'nullable|string',       // Matches Nokia requirement
        'symptoms'    => 'nullable|string|max:500',
        'latitude'    => 'nullable|numeric',
        'longitude'   => 'nullable|numeric',
    ]);

    // Use the Nokia key for internal variable assignment
    $phone = $request->phoneNumber;
    Log::info("Received emergency trigger from phone: {$phone}");

    // ========================
    // 1. KYC Match (Using Nokia Keys)
    // ========================
    $kycResult = null;
    if ($request->phoneNumber && ($request->givenName || $request->familyName || $request->idDocument)) {
        $attributes = [
            'givenName'  => $request->givenName,
            'familyName' => $request->familyName,
            'idDocument' => $request->idDocument,
        ];
        // CamaraService now gets exactly what it needs
        $kycResult = $this->camara->kycMatch($phone, $attributes);
            Log::info("KYC Match result for {$phone}: " . json_encode($kycResult));
    }

    // ========================
    // 2. CAMARA Network APIs (Remains same)
    // ========================
    $networkLocation = $this->camara->getDeviceLocation($phone);
    $reachability    = $this->camara->getReachabilityStatus($phone);
    $qodSession      = $this->camara->createQoDSession($phone, 'DOWNLINK_M_UPLINK_L', 600);

    // ========================
    // 3. AI Triage & 4. Create Incident (Remains same)
    // ========================
    $triageResult = $this->aiTriage->triage(
        $request->symptoms ?? 'Medical Emergency',
        'rural',
        $networkLocation
    );

    $incident = Incident::create([
        'incident_code'  => 'NG-' . now()->format('Ymd-His'),
        'type'           => $triageResult['likely_condition'] ?? 'Emergency',
        'severity'       => $triageResult['severity'] ?? 'medium',
        'description'    => $request->symptoms,
        'latitude'       => $request->latitude,
        'longitude'      => $request->longitude,
        'ai_triage'      => $triageResult,
        'kyc_result'     => $kycResult,
        'qod_session_id' => $qodSession['sessionId'] ?? null,
        'status'         => 'open',
    ]);

    // ========================
    // 5. Create Emergency Alert (MAP Nokia Keys to DB Columns)
    $user = Auth::user();
    // ========================
    $alert = EmergencyAlert::create([
        'incident_id'      => $incident->id,
        'user_id'          => $user ? $user->id : null,
        'phone'            => $phone, // FIX: Use the variable defined at the top ($request->phoneNumber)
        'givenName'        => $request->givenName,
        'familyName'       => $request->familyName,
        'idDocument'       => $request->idDocument,
        'latitude'         => $request->latitude, // Ensure these match your validation keys
        'longitude'        => $request->longitude,
        'network_location' => $networkLocation,
        'symptoms'         => $request->symptoms,
        'status'           => 'pending',
        'session_token'    => $sessionToken ?? null,
        'is_anonymous'     => $request->is_anonymous ?? false,
    ]);

        Log::info('Emergency alert created', [
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'phone' => $alert->phone
        ]);

// In EmergencyController trigger method
try {
    // Attempt real-time broadcast
    broadcast(new \App\Events\EmergencyTriggered($alert, $incident, $triageResult))->toOthers();
} catch (\Exception $e) {
    Log::error("Real-time broadcast failed: " . $e->getMessage());

    // 2. FALLBACK: Send SMS to all 'responder' role users
    // As a math major, think of this as broadcasting to the subset of Responders
    $responders = \App\Models\User::where('role', 'responder')->get();

    foreach ($responders as $responder) {
        $this->smsService->send(
            $responder->phone,
            "NETGUARD ALERT: {$incident->type} reported. Please check the app for location details."
        );
    }
}
    return response()->json([
        'success' => true,
        'alert_id' => $alert->id,
        'kyc_status' => $kycResult ? 'processed' : 'skipped',
        'message' => 'Emergency alert sent successfully!'
    ], 201);
}

    /**
     * Get Active Emergencies (For Responder Dashboard)
     */
    public function active()
    {
        $alerts = EmergencyAlert::whereIn('status', ['pending', 'dispatched', 'on_way'])
            ->with(['incident', 'responder'])
            ->latest()
            ->get();

        return response()->json(['data' => $alerts]);
    }

    /**
 * Dispatch Responder to Alert
 */
public function dispatchAlert(Request $request, $id)
{
    $alert = EmergencyAlert::findOrFail($id);
    $responder = $request->user(); // Authenticated responder

    $alert->update([
        'status' => 'dispatched',
        'responder_id' => $responder->id,
    ]);

    // THE HANDSHAKE
    try {
        // This triggers the responder.coming event on the private channel
        broadcast(new \App\Events\ResponderDispatched($alert, $responder))->toOthers();
    } catch (\Exception $e) {
        Log::error("Handshake broadcast failed: " . $e->getMessage());
    }

    return response()->json([
        'success' => true,
        'message' => 'Victim notified.',
        'responder' => $responder->given_name // matches responderName in Pinia
    ]);
}

public function getMyRequests() {
    $userId = Auth::id();

    Log::info("Fetching secured requests for User ID: " . $userId);

    $requests = EmergencyAlert::with(['incident', 'responder'])
        ->where('user_id', $userId)
        ->latest()
        ->get();

    return response()->json($requests);
}

 /**
     * Cancel an emergency alert
     */
    public function cancelEmergency(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // Find the alert belonging to the authenticated user
            $alert = EmergencyAlert::where('id', $id)
                ->where('user_id', Auth::id())
                ->whereNull('deleted_at')
                ->first();

            if (!$alert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Emergency alert not found'
                ], 404);
            }

            // Check if alert can be cancelled
            if (!$alert->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This emergency cannot be cancelled because it is already ' . $alert->status
                ], 400);
            }

            // Store responder info before deletion
            $responderId = $alert->responder_id;
            $responder = $responderId ? User::find($responderId) : null;

            // Update alert status
            $alert->status = 'cancelled';
            $alert->cancelled_at = now();
            $alert->cancelled_by = Auth::id();
            $alert->save();

            // Broadcast cancellation event to all listeners
            try {
                broadcast(new EmergencyCancelled($alert, 'victim'))->toOthers();
                Log::info("Cancellation event broadcast for alert {$alert->id}");
            } catch (\Exception $e) {
                Log::warning("Failed to broadcast cancellation: " . $e->getMessage());
            }

            // If responder was assigned, log the notification
            if ($responder) {
                Log::info("Responder {$responder->id} ({$responder->given_name}) notified about cancellation of alert {$alert->id}");

                // You can add push notification or SMS here
                // $responder->notify(new EmergencyCancelledNotification($alert));
            }

            // Delete the alert from database (soft delete)
            $alert->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Emergency request cancelled successfully',
                'data' => [
                    'alert_id' => $alert->id,
                    'status' => 'cancelled'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling emergency: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel emergency request: ' . $e->getMessage()
            ], 500);
        }
    }

}
