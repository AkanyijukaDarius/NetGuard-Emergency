<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CamaraService
{
    protected $apiKey;
    protected $host;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.nokia.api_key');
        $this->host    = config('services.nokia.host');
        $this->baseUrl = config('services.nokia.base_url');
    }

    protected function getHeaders()
    {
        return [
            'X-RapidAPI-Key'  => $this->apiKey,
            'X-RapidAPI-Host' => $this->host,
            'Content-Type'    => 'application/json',
            'Accept'          => 'application/json',
        ];
    }

    /**
     * Unified Safe Wrapper for all API calls
     */
    private function safePost(string $endpoint, array $payload)
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(5)
                ->post($this->baseUrl . $endpoint, $payload);

            if ($response->successful()) {
                // We return the array AND a success flag
                return array_merge($response->json(), ['_internal_success' => true]);
            }

            Log::warning("Camara API rejected request: {$endpoint}", ['status' => $response->status()]);
            return ['_internal_success' => false, 'error' => 'API_REJECTED'];

        } catch (\Exception $e) {
            Log::error("Camara API Connection Failed: " . $e->getMessage());
            return ['_internal_success' => false, 'error' => 'NETWORK_UNREACHABLE'];
        }
    }

    // 1. KYC Match
    public function kycMatch(string $phoneNumber, array $userData = [])
    {
        $payload = [
            'device' => ['phoneNumber' => $phoneNumber],
            'idDocument' => $userData['idDocument'] ?? null,
            'givenName'  => $userData['givenName'] ?? null,
            'familyName' => $userData['familyName'] ?? null,
        ];

        return $this->safePost("/passthrough/camara/v1/kyc-match/kyc-match/v0.3/match", $payload);
    }

    /**
 * 5. SIM Swap Check
 * Retrieves the latest date of a SIM swap to assess identity risk.
 */
public function getSimSwapDate(string $phoneNumber)
{
    $payload = ['phoneNumber' => $phoneNumber];

    $result = $this->safePost("/passthrough/camara/v1/sim-swap/sim-swap/v0/retrieve-date", $payload);

    Log::info("SIM Swap API called for {$phoneNumber}", ['result' => $result]);
    return ($result['_internal_success']) ? $result : null;
}

    // 2. Device Location
    public function getDeviceLocation(string $phoneNumber, int $maxAge = 60)
    {
        $payload = [
            'device' => ['phoneNumber' => $phoneNumber],
            'maxAge' => $maxAge
        ];

        $result = $this->safePost("/location-retrieval/v0/retrieve", $payload);
        Log::info("Device Location API called for {$phoneNumber}", ['result' => $result]);

        return ($result['_internal_success']) ? $result : null;
    }

    // 3. Device Reachability
    public function getReachabilityStatus(string $phoneNumber)
    {
        $payload = ['device' => ['phoneNumber' => $phoneNumber]];
        $result = $this->safePost("/device-status/device-reachability-status/v1/retrieve", $payload);
        Log::info("Device Reachability API called for {$phoneNumber}", ['result' => $result]);
        if (!$result['_internal_success']) {
            // Fallback for an emergency app: assume reachable if API fails
            return ['connectivity' => 'CONNECTED', 'deviceStatus' => 'REACHABLE'];
        }

        return $result;
    }

    // 4. Quality on Demand
  public function createQoDSession(string $phoneNumber, string $qosProfile = 'DOWNLINK_L_UPLINK_L', int $duration = 3600)
{
    $payload = [
        'qosProfile' => $qosProfile,
        'device' => [
            'phoneNumber' => $phoneNumber,
            // The API often requires the device IP to match the session
            'ipv4Address' => [
                'publicAddress' => request()->ip(), // Dynamically get the phone's IP
                'publicPort' => 80
            ],
        ],
        'applicationServer' => [
            // This MUST be the IP of your server receiving the emergency data
            'ipv4Address' => '233.252.0.2',
        ],
        'duration' => $duration,
    ];

    $result = $this->safePost("/quality-on-demand/v1/sessions", $payload);
    Log::info("Quality on Demand API called for {$phoneNumber}", ['result' => $result]);

    return ($result['_internal_success']) ? $result : null;
}
}
