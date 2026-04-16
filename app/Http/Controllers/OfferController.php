<?php

namespace App\Http\Controllers;

use App\Models\OffreStage;
use App\Models\OffreEmploi;
use App\Models\MissionFreelance;
use App\Models\MatchingResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OfferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type'); // internship, employment, freelance
        $user = $request->user('sanctum') ?? $request->user();
        $studentId = null;

        if ($user && in_array($user->role, ['student', 'laureat']) && $user->etudiant) {
            $studentId = $user->etudiant->id_candidat;
        }

        $stages = [];
        $emplois = [];
        $missions = [];

        if (!$type || $type === 'internship') {
            $stages = OffreStage::with(['entreprise.user', 'competences'])
                ->whereIn('statut', ['publie', 'ouvert', 'ouverte', 'actif'])
                ->get()
                ->map(function ($item) use ($studentId) {
                    $item->type = 'internship';
                    if ($studentId) {
                        $match = MatchingResult::where('offer_type', 'stage')
                            ->where('offer_id', $item->id_offre_stage)
                            ->where('candidat_id', $studentId)
                            ->first();
                        $item->matching_score = $match ? $match->score_total : null;
                    }
                    return $item;
                });
        }

        if (!$type || $type === 'employment') {
            $emplois = OffreEmploi::with(['entreprise.user', 'competences'])
                ->whereIn('statut', ['publie', 'ouvert', 'ouverte', 'actif'])
                ->get()
                ->map(function ($item) use ($studentId) {
                    $item->type = 'employment';
                    if ($studentId) {
                        $match = MatchingResult::where('offer_type', 'emploi')
                            ->where('offer_id', $item->id_offre_emploi)
                            ->where('candidat_id', $studentId)
                            ->first();
                        $item->matching_score = $match ? $match->score_total : null;
                    }
                    return $item;
                });
        }

        if (!$type || $type === 'freelance') {
            $missions = MissionFreelance::with(['entreprise.user', 'competences'])
                ->whereIn('statut', ['publie', 'ouvert', 'ouverte', 'actif'])
                ->get()
                ->map(function ($item) use ($studentId) {
                    $item->type = 'freelance';
                    if ($studentId) {
                        $match = MatchingResult::where('offer_type', 'freelance')
                            ->where('offer_id', $item->id_mission)
                            ->where('candidat_id', $studentId)
                            ->first();
                        $item->matching_score = $match ? $match->score_total : null;
                    }
                    return $item;
                });
        }

        $allOffers = collect([...$stages, ...$emplois, ...$missions])->sortByDesc('date_publication')->values();

        return response()->json($allOffers);
    }
}
