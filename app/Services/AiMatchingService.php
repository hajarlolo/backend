<?php

namespace App\Services;

use App\Models\Candidat;
use App\Models\MatchingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiMatchingService
{
    private string $apiKey;
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    /**
     * Evaluate a candidate against a specific offer using Gemini.
     * @param Candidat $candidat
     * @param $offer
     * @param string $offer_type 'emploi', 'stage', or 'freelance'
     * @return MatchingResult|null
     */
    public function evaluateCandidateMatch(Candidat $candidat, $offer, string $offer_type): ?MatchingResult
    {
        if (empty($this->apiKey)) {
            Log::error('Matching Error: GEMINI_API_KEY is not set');
            return null;
        }

        // Extrapolate candidate info
        $candidat->loadMissing(['competences', 'formations', 'experiences']);
        
        $candidateDesc = "Candidate ID: {$candidat->id_candidat}\n";
        $candidateDesc .= "Location: {$candidat->city}, {$candidat->country}\n";
        $candidateDesc .= "Skills: " . $candidat->competences->pluck('nom')->join(', ') . "\n";
        
        $candidateDesc .= "Experiences:\n";
        foreach ($candidat->experiences as $exp) {
            $candidateDesc .= "- {$exp->titre} at {$exp->entreprise_nom} ({$exp->type})\n";
        }
        
        $candidateDesc .= "Formations:\n";
        foreach ($candidat->formations as $fm) {
            $candidateDesc .= "- {$fm->diplome} in {$fm->filiere} ({$fm->niveau})\n";
        }

        // Extrapolate offer info
        $offer->loadMissing(['competences']);
        $offerId = null;
        $offerTitle = "";
        
        if ($offer_type === 'emploi') {
            $offerId = $offer->id_offre_emploi;
            $offerTitle = $offer->poste;
        } elseif ($offer_type === 'stage') {
            $offerId = $offer->id_offre_stage;
            $offerTitle = $offer->titre;
        } elseif ($offer_type === 'freelance') {
            $offerId = $offer->id_mission;
            $offerTitle = $offer->titre;
        }

        $offerDesc = "Offer Type: {$offer_type}\n";
        $offerDesc .= "Title: {$offerTitle}\n";
        $offerDesc .= "Location: {$offer->city}, {$offer->country}\n";
        $offerDesc .= "Skills Required: " . $offer->competences->pluck('nom')->join(', ') . "\n";
        $offerDesc .= "Description: {$offer->description}\n";

        // Build prompt
        $prompt = "You are an expert HR AI for a recruitment platform. Your goal is to evaluate the match between a candidate and a job offer.\n\n";
        $prompt .= "== CANDIDATE ==\n{$candidateDesc}\n\n";
        $prompt .= "== OFFER ==\n{$offerDesc}\n\n";
        $prompt .= "Based on the candidate's skills, experiences, and location relative to the offer's requirements, generate exactly four percentage scores (between 0 and 100) representing their match.\n";
        $prompt .= "Respond ONLY with a valid JSON object. Do not include markdown code block syntax. Format:\n";
        $prompt .= "{\n  \"score_total\": 85,\n  \"score_skills\": 90,\n  \"score_experience\": 80,\n  \"score_location\": 100\n}";

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.2,
                "responseMimeType" => "application/json"
            ]
        ];

        try {
            $response = Http::withoutVerifying()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl . '?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    Log::error('Matching Error: Unexpected API response format', $data);
                    return null;
                }
                
                $jsonResponseStr = trim($data['candidates'][0]['content']['parts'][0]['text']);
                
                // Ensure strictly JSON string
                if (str_starts_with($jsonResponseStr, '```json')) {
                    $jsonResponseStr = str_replace(['```json', '```'], '', $jsonResponseStr);
                }
                
                $scores = json_decode($jsonResponseStr, true);

                if (!$scores || !isset($scores['score_total'])) {
                    Log::error('Matching Error: Failed to decode JSON scores', ['raw' => $jsonResponseStr]);
                    return null;
                }

                return MatchingResult::updateOrCreate(
                    [
                        'candidat_id' => $candidat->id_candidat,
                        'offer_id' => $offerId,
                        'offer_type' => $offer_type,
                    ],
                    [
                        'score_total' => $scores['score_total'] ?? 0,
                        'score_skills' => $scores['score_skills'] ?? 0,
                        'score_experience' => $scores['score_experience'] ?? 0,
                        'score_location' => $scores['score_location'] ?? 0,
                    ]
                );
            } else {
                Log::error('Matching API Failed', $response->json());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Matching Exception: ' . $e->getMessage());
            return null;
        }
    }
}
