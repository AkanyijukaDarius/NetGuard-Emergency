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
use App\Events\ResponderDispatched;
use Illuminate\Support\Facades\Auth;
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
     * AUTH: Register Responder or User
     */
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
            return response()->json(['success' => false, 'message' => 'Invalid responder authorization code.'], 403);
        }

        $user = User::create([
            'phone' => $validated['phone'],
            'given_name' => $validated['given_name'],
            'family_name' => $validated['family_name'],
            'id_document' => $validated['id_document'],
            'role' => $validated['role'],
            'name' => $validated['given_name'] . ' ' . $validated['family_name'],
            'email' => $validated['phone'] . '@netguard.com',
            'password' => Hash::make($validated['phone']),
        ]);

        return response()->json([
            'success' => true,
            'token' => $user->createToken('netguard_token')->plainTextToken,
            'user' => $user
        ], 201);
    }

    /**
 * AUTH: Login existing User or Responder
 */
public function login(Request $request)
{
    if (!$request->has('password') && $request->has('phone')) {
        $request->merge(['password' => $request->phone]);
    }

    $validated = $request->validate([
        'phone' => 'required|string',
        'password' => 'required|string',
    ]);

    $user = User::where('phone', $validated['phone'])->first();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'User not found.'], 401);
    }

    try {
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }
    } catch (\RuntimeException $e) {
        if ($validated['password'] === $user->password) {
            $user->password = Hash::make($validated['password']);
            $user->save();
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }
    }

    return response()->json([
        'success' => true,
        'token' => $user->createToken('netguard_token')->plainTextToken,
        'user' => $user
    ]);
}

    /**
     * MAIN: Trigger Emergency (Nokia CAMARA + AI Triage)
     */
public function trigger(Request $request)
{
    $request->validate([
        'phoneNumber' => 'required|string',
        'givenName'   => 'required|string',
        'familyName'  => 'required|string',
        'latitude'    => 'required|numeric',
        'longitude'   => 'required|numeric',
        'symptoms'    => 'nullable|string',
    ]);

    $phone = $request->phoneNumber;

    //  Security Check: SIM Swap Risk
    $simSwapData = $this->camara->getSimSwapDate($phone);
    $isHighRisk = false;
    if (isset($simSwapData['latestSimSwapDate'])) {
        $isHighRisk = \Carbon\Carbon::parse($simSwapData['latestSimSwapDate'])->diffInHours(now()) < 48;
    }

    $networkLocation = $this->camara->getDeviceLocation($phone);
    $reachabilityData = $this->camara->getReachabilityStatus($phone);
    $isReachable = data_get($reachabilityData, 'result.reachable', false);
    $connectivity = data_get($reachabilityData, 'result.connectivity', []);
    $hasData = in_array('DATA', $connectivity);

    // AI Triage
    $triageResult = $this->aiTriage->triage($request->symptoms, 'urban', $networkLocation, $isHighRisk);

    $incident = Incident::create([
        'incident_code'   => 'NG-' . now()->format('Ymd-His'),
        'type'            => $triageResult['likely_condition'] ?? 'Emergency',
        'severity'        => $triageResult['severity'] ?? 'medium',
        'description'     => $request->symptoms,
        'latitude'        => $request->latitude,
        'longitude'       => $request->longitude,
        'ai_triage'       => $triageResult,
        'sim_swap_result' => $simSwapData,
        'status'          => 'open',
    ]);

    $alert = EmergencyAlert::create([
        'incident_id'         => $incident->id,
        'user_id'             => Auth::id(),
        'phone'               => $phone,
        'givenName'           => $request->givenName,
        'familyName'          => $request->familyName,
        'latitude'            => $request->latitude,
        'longitude'           => $request->longitude,
        'network_location'    => $networkLocation,
        'status'              => 'pending',
        'session_token'       => Str::uuid(),
        'reachability_status' => ($isReachable && $hasData) ? 'data' : 'sms',
        'connectivity_type'   => $connectivity[0] ?? 'OFFLINE',
    ]);

    broadcast(new EmergencyTriggered($alert, $incident, $triageResult))->toOthers();

    return response()->json(['success' => true, 'alert_id' => $alert->id], 201);
}
   //active alerts
public function active()
{
    $currentResponder = Auth::user();
    $rLocRaw = $this->camara->getDeviceLocation($currentResponder->phone);
    $rLoc = is_string($rLocRaw) ? json_decode($rLocRaw, true) : (array) $rLocRaw;

    $rLat = data_get($rLoc, 'result.area.center.latitude') ?? data_get($rLoc, 'area.center.latitude');
    $rLng = data_get($rLoc, 'result.area.center.longitude') ?? data_get($rLoc, 'area.center.longitude');

    $alerts = EmergencyAlert::whereIn('status', ['pending', 'dispatched', 'on_way'])
        ->with(['incident', 'responder'])
        ->latest()
        ->get()
        ->map(function ($alert) use ($rLat, $rLng) {
            $alertData = $alert->toArray();

            $alertData['incident'] = $alert->incident;
            $alertData['responder'] = $alert->responder;

            $alertData['responder_api_lat'] = $rLat;
            $alertData['responder_api_lng'] = $rLng;

            $vLoc = $alert->network_location;

            $alertData['victim_lat'] = data_get($vLoc, 'result.area.center.latitude')
                              ?? data_get($vLoc, 'area.center.latitude')
                              ?? $alert->latitude;

            $alertData['victim_lng'] = data_get($vLoc, 'result.area.center.longitude')
                              ?? data_get($vLoc, 'area.center.longitude')
                              ?? $alert->longitude;

            $alertData['accuracy_radius'] = data_get($vLoc, 'result.area.radius')
                                   ?? data_get($vLoc, 'area.radius')
                                   ?? 15;

            return $alertData;
        });

    return response()->json(['success' => true, 'data' => $alerts]);
}



    /**
     * RESPONDER: Dispatch to Incident
     */
    public function dispatchAlert(Request $request, $id)
    {
        $alert = EmergencyAlert::findOrFail($id);
        $responder = Auth::user();

        $alert->update([
            'status' => 'dispatched',
            'responder_id' => $responder->id,
        ]);

        broadcast(new ResponderDispatched($alert, $responder))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Victim notified.',
            'responder_name' => $responder->given_name
        ]);
    }

    //resolved/dispatched alerts
  public function resolved()
{
    $alerts = EmergencyAlert::where('status', 'dispatched')
        ->where('responder_id', Auth::id())
        ->with(['incident'])
        ->latest('updated_at')
        ->get()
        ->map(function ($alert) {
            $alertData = $alert->toArray();
            $alertData['incident'] = $alert->incident;

            $vLoc = $alert->network_location;

            $alertData['victim_lat'] = data_get($vLoc, 'result.area.center.latitude')
                              ?? data_get($vLoc, 'area.center.latitude')
                              ?? $alert->latitude;

            $alertData['victim_lng'] = data_get($vLoc, 'result.area.center.longitude')
                              ?? data_get($vLoc, 'area.center.longitude')
                              ?? $alert->longitude;

            $alertData['accuracy_radius'] = data_get($vLoc, 'result.area.radius')
                                   ?? data_get($vLoc, 'area.radius')
                                   ?? 15;

            return $alertData;
        });

    return response()->json(['success' => true, 'data' => $alerts]);
}



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

    return response()->json([
        'success' => true,
        'data' => $requests
    ]);
}

    /**
     * VICTIM: Cancel Alert
     */
    public function cancelEmergency(Request $request, $id)
    {
        $alert = EmergencyAlert::where('id', $id)->where('user_id', Auth::id())->first();

        if ($alert) {
            $alert->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            broadcast(new EmergencyCancelled($alert))->toOthers();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }
}
