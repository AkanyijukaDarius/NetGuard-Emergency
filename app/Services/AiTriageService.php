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

    public function triage(
        string  $symptoms,
        string  $locationType       = 'rural',
        ?array  $networkLocation    = null,
        bool    $isHighRiskIdentity = false
    ): array {
        // Guard — never let empty string or null reach the API
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
            . "Return ONLY a valid JSON object:\n"
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
                    Log::info('AI triage completed', [
                        'severity'  => $decoded['severity'] ?? 'unknown',
                        'condition' => $decoded['likely_condition'] ?? 'unknown',
                    ]);
                    return $decoded;
                }

                Log::warning('Groq returned invalid JSON', ['content' => $content]);
            } else {
                Log::warning('Groq request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Groq API error: ' . $e->getMessage());
        }

        return $this->fallbackTriage($symptoms);
    }

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
                    'Keep patient conscious by talking to them',
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
                    'Move patient away from heat source',
                    'Cool burns with clean cool water for 10 minutes',
                    'Do not use ice or butter on burns',
                    'Cover loosely with clean cloth',
                ],
                'estimated_response_priority' => 'urgent',
                'reasoning'                   => 'Burns require immediate cooling and medical attention',
            ];
        }

        if (Str::contains($lower, ['poison', 'snake', 'chemical', 'swallowed'])) {
            return [
                'severity'                    => 'high',
                'likely_condition'            => 'Poisoning or Toxic Exposure',
                'recommended_responder'       => 'ambulance',
                'first_aid_tips'              => [
                    'Do not induce vomiting unless advised',
                    'Keep patient still and calm',
                    'Note what was ingested if known',
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
                'Prepare for transfer to nearest clinic',
            ],
            'estimated_response_priority' => 'soon',
            'reasoning'                   => 'Standard case requiring community health response',
        ];
    }
}
