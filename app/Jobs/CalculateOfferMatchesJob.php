<?php

namespace App\Jobs;

use App\Models\Candidat;
use App\Services\AiMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateOfferMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $offer;
    public string $offerType;

    public function __construct($offer, string $offerType)
    {
        $this->offer = $offer;
        $this->offerType = $offerType;
    }

    public function handle(AiMatchingService $matchingService): void
    {
        $candidats = Candidat::with(['competences', 'formations', 'experiences'])->get();

        foreach ($candidats as $candidat) {
            $matchingService->evaluateCandidateMatch($candidat, $this->offer, $this->offerType);
            // Throttle to avoid Gemini API limits (if necessary)
            usleep(500000); // 0.5 sec delay
        }
    }
}
