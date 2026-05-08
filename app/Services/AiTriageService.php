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
        $this->apiKey = config('services.groq.api_key');    }

    /**
     * Perform AI Triage using Groq
     */
    public function triage(string $symptoms, string $locationType = 'rural', ?array $networkLocation = null, bool $isHighRiskIdentity = false)    {
        if (empty($this->apiKey)) {
            return $this->fallbackTriage($symptoms);
        }

        $systemPrompt = "You are an expert medical triage AI for Uganda.
Focus on common local emergencies: boda-boda accidents (trauma), maternal complications, and rural access issues.
Village Health Teams (VHT) are often the fastest first responders in villages.";

        $userPrompt = "Analyze this emergency:\n"
            . "Symptoms: " . ($symptoms ?: "Unknown medical issue") . "\n"
            . "Location type: " . $locationType . "\n"
            . "Security Alert: " . ($isHighRiskIdentity ? "HIGH RISK" : "SECURE") . "\n"
            . "Network Location: " . ($networkLocation ? json_encode($networkLocation) : "Unavailable") . "\n\n"
            . "Return ONLY a valid JSON object with this structure:\n"
            . "{
                \"severity\": \"low|medium|high|critical\",
                \"likely_condition\": \"Short clear diagnosis\",
                \"recommended_responder\": \"VHT|trained_boda|clinic|ambulance\",
                \"first_aid_tips\": [\"tip one\", \"tip two\"],
                \"estimated_response_priority\": \"immediate|urgent|soon\",
                \"reasoning\": \"Brief explanation\"
              }";

        try {
            $response = Http::timeout(8) // Don't wait too long
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => 'llama-3.3-70b-versatile',   // Fast & reliable
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt]
                    ],
                    'temperature' => 0.3,
                    'max_tokens'  => 700,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $decoded = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (\Exception $e) {
            Log::error('Groq API Error: ' . $e->getMessage());
        }

        // Fallback if Groq fails or returns bad JSON
        return $this->fallbackTriage($symptoms);
    }

    /**
     * Reliable fallback when AI is unavailable
     */
    private function fallbackTriage(string $symptoms)
    {
        $lower = Str::lower($symptoms ?? '');

        if (Str::contains($lower, ['maternal', 'pregnant', 'labor', 'bleeding'])) {
            return [
                'severity' => 'critical',
                'likely_condition' => 'Suspected Maternal Emergency',
                'recommended_responder' => 'ambulance',
                'first_aid_tips' => ['Keep patient calm', 'Do not move if bleeding', 'Monitor breathing'],
                'estimated_response_priority' => 'immediate',
                'reasoning' => 'High-risk maternal case common in rural Uganda'
            ];
        }

        if (Str::contains($lower, ['boda', 'accident', 'crash', 'injury', 'trauma'])) {
            return [
                'severity' => 'high',
                'likely_condition' => 'Trauma from Road Accident',
                'recommended_responder' => 'trained_boda',
                'first_aid_tips' => ['Control bleeding', 'Immobilize injured part', 'Check consciousness'],
                'estimated_response_priority' => 'urgent',
                'reasoning' => 'Boda-boda accidents are leading trauma cause'
            ];
        }

        return [
            'severity' => 'medium',
            'likely_condition' => 'General Medical Emergency',
            'recommended_responder' => 'VHT',
            'first_aid_tips' => ['Keep patient comfortable', 'Note symptoms', 'Prepare for transfer'],
            'estimated_response_priority' => 'soon',
            'reasoning' => 'Standard case requiring community response'
        ];
    }
}
