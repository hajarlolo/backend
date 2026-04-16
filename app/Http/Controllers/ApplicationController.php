<?php

namespace App\Http\Controllers;

use App\Models\PostulationStage;
use App\Models\PostulationEmploi;
use App\Models\PostulationFreelance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;
use App\Models\OffreEmploi;
use App\Models\OffreStage;
use App\Models\MissionFreelance;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->candidat ?? $user->etudiant;

        if (!$student) {
            return response()->json(['message' => 'Profil étudiant non trouvé'], 404);
        }

        $type = $request->query('type'); // internship, employment, freelance
        $status = $request->query('status'); // en_attente, acceptée, refusée
        $id_cand = $student->id_candidat ?? $student->id_etudiant;

        $stages = [];
        $emplois = [];
        $missions = [];

        if (!$type || $type === 'internship') {
            $query = PostulationStage::with('offre.entreprise')
                ->where('id_candidat', $id_cand);
            if ($status) $query->where('statut', $status);
            $stages = $query->get()->map(function ($item) {
                return [
                    'type' => 'internship',
                    'offer_title' => $item->offre->titre ?? $item->offre->poste ?? 'N/A',
                    'company_name' => $item->offre->entreprise->nom ?? 'Entreprise',
                    'id_entreprise' => $item->offre->id_entreprise,
                    'id_candidat' => $item->id_candidat,
                    'statut' => $item->statut,
                    'date_postulation' => $item->date_postulation,
                    'documents' => $item->documents,
                    'offreStage' => $item->offre
                ];
            });
        }

        if (!$type || $type === 'employment') {
            $query = PostulationEmploi::with('offre.entreprise')
                ->where('id_candidat', $id_cand);
            if ($status) $query->where('statut', $status);
            $emplois = $query->get()->map(function ($item) {
                return [
                    'type' => 'employment',
                    'offer_title' => $item->offre->poste ?? $item->offre->titre ?? 'N/A',
                    'company_name' => $item->offre->entreprise->nom ?? 'Entreprise',
                    'id_entreprise' => $item->offre->id_entreprise,
                    'id_candidat' => $item->id_candidat,
                    'statut' => $item->statut,
                    'date_postulation' => $item->date_postulation,
                    'documents' => $item->documents,
                    'offreEmploi' => $item->offre
                ];
            });
        }

        if (!$type || $type === 'freelance') {
            $query = PostulationFreelance::with('mission.entreprise')
                ->where('id_candidat', $id_cand);
            if ($status) $query->where('statut', $status);
            $missions = $query->get()->map(function ($item) {
                return [
                    'type' => 'freelance',
                    'offer_title' => $item->mission->titre ?? $item->mission->poste ?? 'N/A',
                    'company_name' => $item->mission->entreprise->nom ?? 'Entreprise',
                    'id_entreprise' => $item->mission->id_entreprise,
                    'id_candidat' => $item->id_candidat,
                    'statut' => $item->statut,
                    'date_postulation' => $item->date_postulation,
                    'documents' => $item->documents,
                    'missionFreelance' => $item->mission
                ];
            });
        }

        $allApplications = collect([...$stages, ...$emplois, ...$missions])
            ->sortByDesc('date_postulation')
            ->values();

        return response()->json($allApplications);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->candidat ?? $user->etudiant; // Handle both candidate/student names

        if (!$student) {
            return response()->json(['message' => 'Profil étudiant non trouvé'], 404);
        }

        $validated = $request->validate([
            'offer_id' => 'required',
            'type' => 'required|in:internship,employment,freelance',
            'files.*' => 'nullable|file|max:5120',
        ]);

        $offerType = $validated['type'];
        $offerId = $validated['offer_id'];
        $id_cand = $student->id_candidat ?? $student->id_etudiant;

        // Check if already applied
        $exists = false;
        if ($offerType === 'internship') {
            $exists = PostulationStage::where('id_offre_stage', $offerId)->where('id_candidat', $id_cand)->exists();
        } elseif ($offerType === 'employment') {
            $exists = PostulationEmploi::where('id_offre_emploi', $offerId)->where('id_candidat', $id_cand)->exists();
        } elseif ($offerType === 'freelance') {
            $exists = PostulationFreelance::where('id_mission', $offerId)->where('id_candidat', $id_cand)->exists();
        }

        if ($exists) {
            return response()->json(['message' => 'Vous avez déjà postulé à cette offre'], 400);
        }

        // Handle file uploads
        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('applications/docs/' . $user->id, 'public');
                $uploadedFiles[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => Storage::url($path)
                ];
            }
        }

        $data = [
            'id_candidat' => $id_cand,
            'statut' => 'en_attente',
            'date_postulation' => now(),
            'documents' => $uploadedFiles
        ];

        try {
            if ($offerType === 'internship') {
                $data['id_offre_stage'] = $offerId;
                PostulationStage::create($data);
            } elseif ($offerType === 'employment') {
                $data['id_offre_emploi'] = $offerId;
                PostulationEmploi::create($data);
            } elseif ($offerType === 'freelance') {
                $data['id_mission'] = $offerId;
                PostulationFreelance::create($data);
            }
        } catch (\Exception $e) {
            \Log::error("Application store error: " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'enregistrement de votre candidature: ' . $e->getMessage()], 500);
        }

        // Send notification to company
        try {
            $offerModel = match($offerType) {
                'employment' => OffreEmploi::class,
                'internship' => OffreStage::class,
                'freelance'  => MissionFreelance::class,
                default      => null
            };

            if ($offerModel) {
                $offerData = $offerModel::with('entreprise')->find($offerId);
                $notifType = match($offerType) {
                    'employment' => 'offre_emploi',
                    'internship' => 'offre_stage',
                    'freelance'  => 'mission',
                    default      => 'general'
                };

                if ($offerData && $offerData->entreprise && $offerData->entreprise->id_user) {
                    Notification::create([
                        'user_id' => $offerData->entreprise->id_user,
                        'type'    => $notifType,
                        'contenu' => "Nouveau candidat : Un étudiant a postulé pour votre offre " . ($offerType === 'freelance' ? 'freelance' : ($offerType === 'internship' ? 'de stage' : "d'emploi")) . " : \"" . ($offerData->titre ?? $offerData->poste ?? 'votre offre') . "\".",
                        'read'    => false,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error sending application notification: " . $e->getMessage());
        }

        return response()->json(['message' => 'Candidature envoyée avec succès'], 201);
    }
}
