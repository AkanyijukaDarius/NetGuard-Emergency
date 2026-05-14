<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AiTriageService
{
    protected string|null $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
    }

    // CONTEXTUAL LAYER -> Medical Triage Analysis
    public function triage(
        string  $symptoms,
        string  $locationType       = 'urban',
        ?array  $networkLocation    = null,
        bool    $isHighRiskIdentity = false
    ): array {
        $symptoms = (string) ($symptoms ?: 'Medical Emergency');

        if (empty($this->apiKey)) {
            return $this->fallbackTriage($symptoms);
        }

        $systemPrompt = "You are an expert medical triage AI for Uganda. "
            . "Focus on common local emergencies: boda-boda accidents (trauma), "
            . "maternal complications, and rural access issues. "
            . "Village Health Teams (VHT) are often the fastest first responders.";

        $userPrompt = "Analyze this emergency:\n"
            . "Symptoms: {$symptoms}\n"
            . "Location type: {$locationType}\n"
            . "Security alert: " . ($isHighRiskIdentity ? "HIGH RISK — SIM swapped recently" : "SECURE") . "\n"
            . "Network location: " . ($networkLocation ? json_encode($networkLocation) : "Unavailable") . "\n\n"
            . "Return ONLY a valid JSON object with this structure:\n"
            . '{"severity":"low|medium|high|critical",'
            . '"likely_condition":"Short clear diagnosis",'
            . '"recommended_responder":"VHT|trained_boda|clinic|ambulance",'
            . '"first_aid_tips":["tip one","tip two"],'
            . '"estimated_response_priority":"immediate|urgent|soon",'
            . '"reasoning":"Brief explanation"}';

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'           => 'llama-3.3-70b-versatile',
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'temperature'     => 0.3,
                    'max_tokens'      => 700,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    Log::info('AI Triage completed successfully', [
                        'severity'  => $decoded['severity'] ?? 'unknown',
                        'condition' => $decoded['likely_condition'] ?? 'unknown',
                    ]);
                    return $decoded;
                }
            }
        } catch (\Exception $e) {
            Log::error('Groq API error in triage: ' . $e->getMessage());
        }

        return $this->fallbackTriage($symptoms);
    }

   // AGENTIC ORCHESTRATION LAYER -> Decision-making based on Triage
    public function agenticOrchestrate(
        array $triageResult,
        array $reachabilityData = [],
        ?array $networkLocation = null,
        string $phoneNumber
    ): array {
        $severity = strtolower($triageResult['severity'] ?? 'medium');
        $condition = $triageResult['likely_condition'] ?? '';

        $actions = [
            'qod_triggered'      => false,
            'qos_profile'        => null,
            'priority_escalated' => false,
            'recommended_responder' => $triageResult['recommended_responder'] ?? 'VHT',
            'reasoning'          => '',
        ];

        $isHighPriority = in_array($severity, ['high', 'critical']);
        $connectivityDegraded = $this->isConnectivityDegraded($reachabilityData);

        if ($isHighPriority || $connectivityDegraded) {
            $actions['qos_profile'] = $this->determineQosProfile($severity, $condition);
            $actions['qod_triggered'] = true;
            $actions['priority_escalated'] = true;

            $actions['reasoning'] = $isHighPriority
                ? "High/Critical severity detected → Autonomous QoD activation"
                : "Poor connectivity detected → QoD boosted for reliability";
        }

        $lowerCondition = Str::lower($condition);
        if (Str::contains($lowerCondition, ['maternal', 'labor', 'pregnant', 'birth', 'bleeding'])) {
            $actions['recommended_responder'] = 'ambulance';
        } elseif (Str::contains($lowerCondition, ['boda', 'accident', 'crash', 'trauma', 'injury'])) {
            $actions['recommended_responder'] = 'trained_boda';
        } elseif (Str::contains($lowerCondition, ['burn', 'fire', 'poison', 'snake'])) {
            $actions['recommended_responder'] = 'clinic';
        }

        return array_merge($triageResult, ['agentic_actions' => $actions]);
    }

  // Helper to determine QoS profile based on severity and condition
    public function determineQosProfile(string $severity, string $condition = ''): string
    {
        $lowerCondition = Str::lower($condition);

        if ($severity === 'critical' ||
            Str::contains($lowerCondition, ['maternal', 'labor', 'cardiac', 'unconscious', 'heavy bleeding', 'birth'])) {
            return 'DOWNLINK_L_UPLINK_L';
        }

        if ($severity === 'high' ||
            Str::contains($lowerCondition, ['boda', 'accident', 'trauma', 'burn', 'poison', 'snake', 'injury'])) {
            return 'DOWNLINK_L_UPLINK_M';
        }

        return 'DOWNLINK_M_UPLINK_L';
    }

  // Helper to assess connectivity status from reachability data
    private function isConnectivityDegraded(array $reachData): bool
    {
        $connectivity = data_get($reachData, 'result.connectivity', [])
                     ?? data_get($reachData, 'connectivity', []);

        return empty($connectivity) || !in_array('DATA', $connectivity);
    }

  // Fallback triage layer for when AI service is unavailable
    private function fallbackTriage(string $symptoms): array
    {
        $lower = Str::lower($symptoms ?: '');

        if (Str::contains($lower, ['maternal', 'pregnant', 'labor', 'bleeding', 'birth'])) {
            return [
                'severity'                    => 'critical',
                'likely_condition'            => 'Suspected Maternal Emergency',
                'recommended_responder'       => 'ambulance',
                'first_aid_tips'              => [
                    'Keep patient calm and lying down',
                    'Do not move if bleeding heavily',
                    'Monitor breathing and pulse',
                    'Keep warm with a blanket',
                ],
                'estimated_response_priority' => 'immediate',
                'reasoning'                   => 'High-risk maternal case common in rural Uganda',
            ];
        }

        if (Str::contains($lower, ['boda', 'accident', 'crash', 'injury', 'trauma', 'road'])) {
            return [
                'severity'                    => 'high',
                'likely_condition'            => 'Trauma from Road Accident',
                'recommended_responder'       => 'trained_boda',
                'first_aid_tips'              => [
                    'Control any visible bleeding with pressure',
                    'Do not move the patient if spine injury suspected',
                    'Check and maintain open airway',
                ],
                'estimated_response_priority' => 'urgent',
                'reasoning'                   => 'Boda-boda accidents are the leading trauma cause in Uganda',
            ];
        }

        if (Str::contains($lower, ['fire', 'burn', 'smoke'])) {
            return [
                'severity'                    => 'high',
                'likely_condition'            => 'Burns or Fire-related Injury',
                'recommended_responder'       => 'clinic',
                'first_aid_tips'              => [
                    'Move away from heat source',
                    'Cool burns with clean cool water',
                    'Cover loosely with clean cloth',
                ],
                'estimated_response_priority' => 'urgent',
                'reasoning'                   => 'Burns require immediate cooling',
            ];
        }

        if (Str::contains($lower, ['poison', 'snake', 'chemical'])) {
            return [
                'severity'                    => 'high',
                'likely_condition'            => 'Poisoning or Toxic Exposure',
                'recommended_responder'       => 'ambulance',
                'first_aid_tips'              => [
                    'Do not induce vomiting unless advised',
                    'Keep patient still and calm',
                    'Monitor breathing closely',
                ],
                'estimated_response_priority' => 'urgent',
                'reasoning'                   => 'Poisoning requires urgent medical evaluation',
            ];
        }

        return [
            'severity'                    => 'medium',
            'likely_condition'            => 'General Medical Emergency',
            'recommended_responder'       => 'VHT',
            'first_aid_tips'              => [
                'Keep patient comfortable and calm',
                'Note all symptoms carefully',
                'Do not give food or water unless conscious',
            ],
            'estimated_response_priority' => 'soon',
            'reasoning'                   => 'Standard case requiring community health response',
        ];
    }
}
