<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CamaraService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ResponderController extends Controller
{
    protected $camara;

    public function __construct(CamaraService $camara)
    {
        $this->camara = $camara;
    }

    public function getLiveResponders()
    {
        $victim = Auth::user();
        if (!$victim) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }

        // Get victim location once
        Log::info("Getting location for phone: " . $victim->phone);
        $vLocRaw = $this->camara->getDeviceLocation($victim->phone);

        $vLoc = is_string($vLocRaw) ? json_decode($vLocRaw, true) : (array) $vLocRaw;

        $vLat = data_get($vLoc, 'area.center.latitude');
        $vLng = data_get($vLoc, 'area.center.longitude');

        //  alternative paths
        if (!$vLat || !$vLng) {
            $vLat = data_get($vLoc, 'result.area.center.latitude');
            $vLng = data_get($vLoc, 'result.area.center.longitude');
            Log::info("Tried alternative path (result.area.center)");
        }

        Log::info("Victim Coordinates Extracted: Lat: $vLat, Lng: $vLng");

        // Check if coordinates exist
        if (!$vLat || !$vLng) {
            Log::error("Failed to extract coordinates from location data");
            Log::error("Location data structure: " . json_encode($vLoc));
        }

        $responders = User::where('role', 'responder')
            ->select(['id', 'given_name', 'family_name', 'role', 'phone'])
            ->get();

        Log::info("Found " . $responders->count() . " responders");

        $data = $responders->map(function ($res) use ($vLat, $vLng) {
            Log::info("Processing responder: " . $res->phone);

            $cacheKey = "responder_loc_{$res->phone}";

            $locationData = Cache::remember($cacheKey, 120, function () use ($res) {
                return $this->camara->getDeviceLocation($res->phone);
            });

            $reachKey = "responder_reach_{$res->phone}";
            $reachability = Cache::remember($reachKey, 60, function () use ($res) {
                return $this->camara->getReachabilityStatus($res->phone);
            });

            $locationArray = is_string($locationData) ? json_decode($locationData, true) : (array) $locationData;

            $rLat = data_get($locationArray, 'area.center.latitude');
            $rLng = data_get($locationArray, 'area.center.longitude');

            //  alternative path
            if (!$rLat || !$rLng) {
                $rLat = data_get($locationArray, 'result.area.center.latitude');
                $rLng = data_get($locationArray, 'result.area.center.longitude');
            }

            $distance = ($vLat && $vLng && $rLat && $rLng)
                ? round($this->haversine($vLat, $vLng, $rLat, $rLng), 2)
                : null;

            $reachArray = is_string($reachability) ? json_decode($reachability, true) : (array) $reachability;
            Log::info("Decoding reachability for {$res->phone}: " . json_encode($reachArray));

            $isReachable = $reachArray['result']['reachable'] ?? $reachArray['reachable'] ?? false; 

            $connectivityArray = $reachArray['result']['connectivity'] ?? $reachArray['connectivity'] ?? [];
            $connType = $connectivityArray[0] ?? 'OFFLINE';
            $uiStatus = 'Offline';
            if ($isReachable === true || $isReachable === 'true') {
                $uiStatus = (strtoupper($connType) === 'DATA') ? 'Online' : 'SMS Only';
            }

            Log::info("Final Mapping for {$res->phone}: Status: $uiStatus, Conn: $connType");

            return [
                'id'          => $res->id,
                'name'        => "{$res->given_name} {$res->family_name}",
                'role'        => $res->role ?? 'VHT Responder',
                'distance'    => $distance,
                'status'      => $uiStatus,
                'connectivity'=> $connType,
                'phone'       => $res->phone,
                'latitude'    => $rLat,
                'longitude'   => $rLng,
            ];
        });

        // Sort by distance (closest first)
        $sorted = $data->sortBy(fn($item) => $item['distance'] ?? 9999)->values();

        return response()->json([
            'success' => true,
            'data'    => $sorted,
            'victim_location' => [
                'latitude'  => $vLat,
                'longitude' => $vLng
            ]
        ]);
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $r = 6371; // Earth radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
