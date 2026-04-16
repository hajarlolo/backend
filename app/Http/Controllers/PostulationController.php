<?php

namespace App\Http\Controllers;

use App\Models\PostulationEmploi;
use App\Models\PostulationFreelance;
use App\Models\PostulationStage;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PostulationController extends Controller
{
    public function __construct(private readonly \App\Services\UserActivityLogService $userActivityLogService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user || !in_array(strtolower($user->role), ['student', 'laureat'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $candidat = $user->candidat;
        if (!$candidat) {
            return response()->json(['message' => 'Candidate profile not found'], 404);
        }

        $type = $request->query('type');
        $status = $request->query('status');

        $applications = collect();

        if (!$type || $type === 'employment') {
            $query = PostulationEmploi::with(['offre.entreprise'])
                ->where('id_candidat', $candidat->id_candidat);
            if ($status) $query->where('statut', $status);
            
            $pEmploi = $query->get()->map(function($p) {
                return [
                    'offer_id' => $p->id_offre_emploi,
                    'id_entreprise' => $p->offre->id_entreprise,
                    'id_candidat' => $p->id_candidat,
                    'offer_title' => $p->offre->poste ?? $p->offre->titre,
                    'type' => 'employment',
                    'statut' => $p->statut,
                    'date_postulation' => $p->date_postulation->toIso8601String(),
                    'documents' => $p->documents,
                    'offreEmploi' => $p->offre
                ];
            });
            $applications = $applications->concat($pEmploi);
        }

        if (!$type || $type === 'internship') {
            $query = PostulationStage::with(['offre.entreprise'])
                ->where('id_candidat', $candidat->id_candidat);
            if ($status) $query->where('statut', $status);

            $pStage = $query->get()->map(function($p) {
                return [
                    'offer_id' => $p->id_offre_stage,
                    'id_entreprise' => $p->offre->id_entreprise,
                    'id_candidat' => $p->id_candidat,
                    'offer_title' => $p->offre->titre ?? $p->offre->poste,
                    'type' => 'internship',
                    'statut' => $p->statut,
                    'date_postulation' => $p->date_postulation->toIso8601String(),
                    'documents' => $p->documents,
                    'offreStage' => $p->offre
                ];
            });
            $applications = $applications->concat($pStage);
        }

        if (!$type || $type === 'freelance') {
            $query = PostulationFreelance::with(['mission.entreprise'])
                ->where('id_candidat', $candidat->id_candidat);
            if ($status) $query->where('statut', $status);

            $pFreelance = $query->get()->map(function($p) {
                return [
                    'offer_id' => $p->id_mission,
                    'id_entreprise' => $p->mission->id_entreprise,
                    'id_candidat' => $p->id_candidat,
                    'offer_title' => $p->mission->titre ?? $p->mission->poste,
                    'type' => 'freelance',
                    'statut' => $p->statut,
                    'date_postulation' => $p->date_postulation->toIso8601String(),
                    'documents' => $p->documents,
                    'missionFreelance' => $p->mission
                ];
            });
            $applications = $applications->concat($pFreelance);
        }

        $response = $applications->sortByDesc('date_postulation')->values();
        \Illuminate\Support\Facades\Log::info('Applications response', ['data' => $response->toArray()]);
        return response()->json($response);
    }

    public function applyEmploi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_offre_emploi' => 'required|exists:offre_emplois,id_offre_emploi',
            'id_candidat' => 'required|exists:candidats,id_candidat',
            'documents' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $postulation = PostulationEmploi::create([
            'id_offre_emploi' => $request->id_offre_emploi,
            'id_candidat' => $request->id_candidat,
            'date_postulation' => now(),
            'statut' => 'en_attente',
            'documents' => $request->documents,
        ]);

        if ($request->user()) {
            $this->userActivityLogService->log(
                $request->user(), 
                'apply_offer', 
                'offer', 
                $request->id_offre_emploi, 
                "Applied to job offer ID: {$request->id_offre_emploi}"
            );
        }

        // Send notification to company
        try {
            $offre = \App\Models\OffreEmploi::with('entreprise')->find($request->id_offre_emploi);
            if ($offre && $offre->entreprise && $offre->entreprise->id_user) {
                Notification::create([
                    'user_id' => $offre->entreprise->id_user,
                    'type' => 'offre_emploi',
                    'contenu' => "Nouveau candidat : Un étudiant a postulé pour votre offre d'emploi : " . ($offre->poste ?? $offre->titre),
                    'read' => false,
                ]);
                Log::info("Notification sent for applyEmploi");
            }
        } catch (\Exception $e) {
            Log::error("Error in applyEmploi notification: " . $e->getMessage());
        }

        return response()->json($postulation, 201);
    }

    public function applyStage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_offre_stage' => 'required|exists:offre_stages,id_offre_stage',
            'id_candidat' => 'required|exists:candidats,id_candidat',
            'documents' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $postulation = PostulationStage::create([
            'id_offre_stage' => $request->id_offre_stage,
            'id_candidat' => $request->id_candidat,
            'date_postulation' => now(),
            'statut' => 'en_attente',
            'documents' => $request->documents,
        ]);

        if ($request->user()) {
            $this->userActivityLogService->log(
                $request->user(), 
                'apply_offer', 
                'offer', 
                $request->id_offre_stage, 
                "Applied to stage offer ID: {$request->id_offre_stage}"
            );
        }

        // Send notification to company
        try {
            $offre = \App\Models\OffreStage::with('entreprise')->find($request->id_offre_stage);
            if ($offre && $offre->entreprise && $offre->entreprise->id_user) {
                Notification::create([
                    'user_id' => $offre->entreprise->id_user,
                    'type' => 'offre_stage',
                    'contenu' => "Nouveau candidat : Un étudiant a postulé pour votre offre de stage : " . ($offre->titre ?? $offre->poste),
                    'read' => false,
                ]);
                Log::info("Notification sent for applyStage");
            }
        } catch (\Exception $e) {
            Log::error("Error in applyStage notification: " . $e->getMessage());
        }

        return response()->json($postulation, 201);
    }

    public function applyFreelance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_mission' => 'required|exists:mission_freelances,id_mission',
            'id_candidat' => 'required|exists:candidats,id_candidat',
            'documents' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $postulation = PostulationFreelance::create([
            'id_mission' => $request->id_mission,
            'id_candidat' => $request->id_candidat,
            'date_postulation' => now(),
            'statut' => 'en_attente',
            'documents' => $request->documents,
        ]);

        if ($request->user()) {
            $this->userActivityLogService->log(
                $request->user(), 
                'apply_offer', 
                'offer', 
                $request->id_mission, 
                "Applied to freelance mission ID: {$request->id_mission}"
            );
        }

        // Send notification to company
        try {
            $mission = \App\Models\MissionFreelance::with('entreprise')->find($request->id_mission);
            if ($mission && $mission->entreprise && $mission->entreprise->id_user) {
                Notification::create([
                    'user_id' => $mission->entreprise->id_user,
                    'type' => 'mission',
                    'contenu' => "Nouveau candidat : Un étudiant a postulé pour votre mission freelance : " . ($mission->titre ?? $mission->poste),
                    'read' => false,
                ]);
                Log::info("Notification sent for applyFreelance");
            }
        } catch (\Exception $e) {
            Log::error("Error in applyFreelance notification: " . $e->getMessage());
        }

        return response()->json($postulation, 201);
    }

    public function apply(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user || !in_array(strtolower($user->role), ['student', 'laureat'])) {
            return response()->json(['message' => 'Seuls les candidats peuvent postuler.'], 403);
        }

        $candidat = $user->candidat;
        if (!$candidat) {
            return response()->json(['message' => 'Profil candidat introuvable.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required',
            'type' => 'required|in:employment,internship,freelance',
            'files' => 'nullable|array',
            'files.*' => 'file|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $offerId = $request->offer_id;
        $type = $request->type;
        $documents = [];

        Log::info("Entering apply method", ['user_id' => $user->id_user, 'offer_id' => $offerId]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('applications/docs/' . $user->id_user, 'public');
                $documents[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path
                ];
            }
        }

        $postulation = null;
        if ($type === 'employment') {
            $postulation = PostulationEmploi::updateOrCreate(
                ['id_offre_emploi' => $offerId, 'id_candidat' => $candidat->id_candidat],
                [
                    'date_postulation' => now(),
                    'statut' => 'en_attente',
                    'documents' => $documents
                ]
            );
        } elseif ($type === 'internship') {
            $postulation = PostulationStage::updateOrCreate(
                ['id_offre_stage' => $offerId, 'id_candidat' => $candidat->id_candidat],
                [
                    'date_postulation' => now(),
                    'statut' => 'en_attente',
                    'documents' => $documents
                ]
            );
        } else {
            $postulation = PostulationFreelance::updateOrCreate(
                ['id_mission' => $offerId, 'id_candidat' => $candidat->id_candidat],
                [
                    'date_postulation' => now(),
                    'statut' => 'en_attente',
                    'documents' => $documents
                ]
            );
        }

        try {
            $this->userActivityLogService->log(
                $user, 
                'apply_offer', 
                'offer', 
                (int)$offerId, 
                "Postulation soumise pour l'offre {$type} ID: {$offerId}"
            );
        } catch (\Exception $e) {
            Log::error("Logging failed but continuing: " . $e->getMessage());
        }

        // Send notification to company
        try {
            if ($postulation) {
                $offerModel = match($type) {
                    'employment' => \App\Models\OffreEmploi::class,
                    'internship' => \App\Models\OffreStage::class,
                    'freelance'  => \App\Models\MissionFreelance::class,
                    default      => null
                };

                if ($offerModel) {
                    $offerData = $offerModel::find($offerId);
                    $notifType = match($type) {
                        'employment' => 'offre_emploi',
                        'internship' => 'offre_stage',
                        'freelance'  => 'mission',
                        default      => 'general'
                    };

                    if ($offerData && $offerData->entreprise && $offerData->entreprise->id_user) {
                        $targetUserId = $offerData->entreprise->id_user;
                        $titreOffre = $offerData->titre ?? $offerData->poste ?? 'votre offre';
                        $notif = Notification::create([
                            'user_id' => $targetUserId,
                            'type'    => $notifType,
                            'contenu' => "Nouveau candidat : Un étudiant a postulé pour votre offre " . ($type === 'freelance' ? 'freelance' : ($type === 'internship' ? 'de stage' : "d'emploi")) . " : \"{$titreOffre}\".",
                            'read'    => false,
                        ]);
                        Log::info("Notification de postulation envoyée à l'entreprise (User ID: {$targetUserId})", ['notif_id' => $notif->id_notification]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error sending notification: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Candidature envoyée avec succès.',
            'postulation' => $postulation
        ], 201);
    }

    public function getCompanyPostulations(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user || !in_array(strtolower($user->role), ['entreprise', 'company'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $entreprise = $user->entreprise;
        if (!$entreprise) {
            return response()->json(['message' => 'Company profile not found'], 404);
        }

        $type = $request->query('type', 'all');
        $postulations = collect();

        if ($type === 'all' || $type === 'emploi' || $type === 'employment') {
            $emploiIds = $entreprise->offresEmploi()->pluck('id_offre_emploi');
            $pEmploi = PostulationEmploi::with(['candidat.user', 'offre'])
                ->whereIn('id_offre_emploi', $emploiIds)
                ->get()
                ->map(function($p) {
                    return [
                        'etudiant' => [
                            'id_etudiant' => $p->id_candidat,
                            'id_candidat' => $p->id_candidat,
                            'user' => $p->candidat->user
                        ],
                        'offre_titre' => $p->offre->poste ?? $p->offre->titre ?? 'Poste sans titre',
                        'id_offer' => $p->id_offre_emploi,
                        'id_entreprise' => $p->offre->id_entreprise,
                        'id_candidat' => $p->id_candidat,
                        'type' => 'emploi',
                        'statut' => $p->statut,
                        'date' => $p->date_postulation->toIso8601String(),
                        'cv_url' => $p->candidat->cv_url,
                        'documents' => $p->documents
                    ];
                });
            $postulations = $postulations->concat($pEmploi);
        }

        if ($type === 'all' || $type === 'stage' || $type === 'internship') {
            $stageIds = $entreprise->offresStage()->pluck('id_offre_stage');
            $pStage = PostulationStage::with(['candidat.user', 'offre'])
                ->whereIn('id_offre_stage', $stageIds)
                ->get()
                ->map(function($p) {
                    return [
                        'etudiant' => [
                            'id_etudiant' => $p->id_candidat,
                            'id_candidat' => $p->id_candidat,
                            'user' => $p->candidat->user
                        ],
                        'offre_titre' => $p->offre->titre ?? $p->offre->poste,
                        'id_offer' => $p->id_offre_stage,
                        'id_entreprise' => $p->offre->id_entreprise,
                        'id_candidat' => $p->id_candidat,
                        'type' => 'stage',
                        'statut' => $p->statut,
                        'date' => $p->date_postulation->toIso8601String(),
                        'cv_url' => $p->candidat->cv_url,
                        'documents' => $p->documents
                    ];
                });
            $postulations = $postulations->concat($pStage);
        }

        if ($type === 'all' || $type === 'freelance') {
            $missionIds = $entreprise->missions()->pluck('id_mission');
            $pFreelance = PostulationFreelance::with(['candidat.user', 'mission'])
                ->whereIn('id_mission', $missionIds)
                ->get()
                ->map(function($p) {
                    return [
                        'etudiant' => [
                            'id_etudiant' => $p->id_candidat,
                            'id_candidat' => $p->id_candidat,
                            'user' => $p->candidat->user
                        ],
                        'offre_titre' => $p->mission->titre ?? $p->mission->poste ?? 'Mission sans titre',
                        'id_offer' => $p->id_mission,
                        'id_entreprise' => $p->mission->id_entreprise,
                        'id_candidat' => $p->id_candidat,
                        'type' => 'freelance',
                        'statut' => $p->statut,
                        'date' => $p->date_postulation->toIso8601String(),
                        'cv_url' => $p->candidat->cv_url,
                        'documents' => $p->documents
                    ];
                });
            $postulations = $postulations->concat($pFreelance);
        }

        return response()->json($postulations->sortByDesc('date')->values());
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:emploi,stage,freelance,employment,internship',
            'id_offer' => 'required',
            'id_candidat' => 'required',
            'statut' => 'required|in:en_attente,acceptée,refusée,entretien',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $postulation = null;
        $type = $request->type;
        if ($type === 'emploi' || $type === 'employment') {
            $postulation = PostulationEmploi::with(['candidat', 'offre'])->where('id_offre_emploi', $request->id_offer)->where('id_candidat', $request->id_candidat)->firstOrFail();
        } elseif ($type === 'stage' || $type === 'internship') {
            $postulation = PostulationStage::with(['candidat', 'offre'])->where('id_offre_stage', $request->id_offer)->where('id_candidat', $request->id_candidat)->firstOrFail();
        } else {
            $postulation = PostulationFreelance::with(['candidat', 'mission'])->where('id_mission', $request->id_offer)->where('id_candidat', $request->id_candidat)->firstOrFail();
        }

        $postulation->update(['statut' => $request->statut]);

        if ($request->user()) {
            $actionType = $request->statut === 'acceptée' ? 'accept_user' : ($request->statut === 'refusée' ? 'reject_user' : 'update_status');
            $this->userActivityLogService->log(
                $request->user(), 
                $actionType, 
                'application', 
                $request->id_offer, 
                "Application status updated to {$request->statut} for candidat ID: {$request->id_candidat}"
            );

            // Notify candidate
            try {
                if ($postulation && $postulation->candidat && $postulation->candidat->id_user) {
                    $statusLabel = match($request->statut) {
                        'acceptée' => 'acceptée',
                        'refusée' => 'refusée',
                        'entretien' => 'sélectionnée pour un entretien',
                        default => $request->statut
                    };
                    
                    $titreOffre = '';
                    if ($type === 'emploi' || $type === 'employment' || $type === 'stage' || $type === 'internship') {
                        $titreOffre = $postulation->offre->poste ?? $postulation->offre->titre;
                    } else {
                        $titreOffre = $postulation->mission->titre ?? $postulation->mission->poste;
                    }

                    Notification::create([
                        'user_id' => $postulation->candidat->id_user,
                        'type' => 'general',
                        'contenu' => "Mise à jour : Votre candidature pour l'offre \"{$titreOffre}\" a été {$statusLabel}.",
                        'read' => false,
                    ]);
                    Log::info("Notification de statut envoyée au candidat (User ID: {$postulation->candidat->id_user})");
                }
            } catch (\Exception $e) {
                Log::error("Erreur notification candidat: " . $e->getMessage());
            }
        }

        return response()->json($postulation);
    }
}
