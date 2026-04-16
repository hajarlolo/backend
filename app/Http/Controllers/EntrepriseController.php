<?php

namespace App\Http\Controllers;

use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EntrepriseController extends Controller
{
    public function __construct(private readonly \App\Services\UserActivityLogService $userActivityLogService)
    {
    }

    public function index()
    {
        return response()->json(Entreprise::with('user')->get());
    }

    public function show($id)
    {
        $entreprise = Entreprise::with(['user', 'missions', 'offresEmploi', 'offresStage'])->findOrFail($id);
        return response()->json($entreprise);
    }

    public function update(Request $request, $id)
    {
        $entreprise = Entreprise::findOrFail($id);

        // Authorization check: Only the owner or admin can update
        if (auth()->id() !== $entreprise->id_user && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ice' => 'sometimes|string|max:255',
            'email_professionnel' => 'sometimes|email|max:255',
            'localisation' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'telephone' => 'sometimes|string|max:30',
            'secteur_activite' => 'sometimes|string|max:120',
            'taille' => 'sometimes|in:TPE,PME,Grande',
            'site_web' => 'sometimes|url|max:255',
            'logo_url' => 'sometimes|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $entreprise->update($request->all());

        if (auth()->user()) {
            $this->userActivityLogService->log(
                auth()->user(), 
                'update_profile', 
                'user', 
                auth()->id(), 
                "Company updated profile: {$entreprise->nom_entreprise}"
            );
        }

        return response()->json($entreprise->load('user'));
    }

    public function getProfile(Request $request)
    {
        $entreprise = auth()->user()->entreprise;
        if (! $entreprise) {
            return response()->json(['message' => 'Profil introuvable'], 404);
        }
        return response()->json([
            'entreprise' => $entreprise->load('user'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $entreprise = auth()->user()->entreprise;
        
        if (! $entreprise) {
            return response()->json(['message' => 'Profil introuvable'], 404);
        }

        $input = array_map(function ($value) {
            return $value === '' || $value === 'null' ? null : $value;
        }, $request->all());

        $validator = Validator::make($input, [
            'nom' => 'required|string|max:255',
            'ice' => 'nullable|string|max:255',
            'email_professionnel' => 'required|email|max:255',
            'localisation' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'telephone' => 'nullable|string|max:30',
            'secteur_activite' => 'nullable|string|max:120',
            'taille' => 'nullable|in:TPE,PME,Grande',
            'site_web' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::warning('Company Profile Update Validation Failed', $validator->errors()->toArray());
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        
        // Update user (nom)
        auth()->user()->update(['nom' => $data['nom']]);
        unset($data['nom']);
        
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('profiles/companies/' . auth()->id(), 'public');
            $data['logo_url'] = $logoPath;
            unset($data['logo']);
        }

        $entreprise->update($data);

        $this->userActivityLogService->log(
            auth()->user(), 
            'update_profile', 
            'user', 
            auth()->id(), 
            "Company updated profile via dashboard: " . auth()->user()->nom
        );

        return response()->json([
            'message' => 'Profil mis a jour avec succes.',
            'entreprise' => $entreprise->load('user'),
        ]);
    }

    public function getDashboardStats(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifie.'], 401);
        }

        $entreprise = $user->entreprise;
        if (!$entreprise) {
            return response()->json(['message' => 'Profil entreprise introuvable.'], 404);
        }

        $total_offres_emploi = $entreprise->offresEmploi()->count();
        $total_offres_stage = $entreprise->offresStage()->count();
        $total_missions_freelance = $entreprise->missions()->count();

        // Active (open) offers
        $offres_ouvertes = $entreprise->offresEmploi()->whereIn('statut', ['ouvert', 'ouvertes', 'en cours', 'actif', 'publie'])->count() +
                          $entreprise->offresStage()->whereIn('statut', ['ouvert', 'ouvertes', 'en cours', 'actif', 'publie'])->count() +
                          $entreprise->missions()->whereIn('statut', ['ouvert', 'ouvertes', 'en cours', 'actif', 'publie'])->count();

        // Count postulations across all types
        $total_postulations = 0;
        
        $emploiIds = $entreprise->offresEmploi()->pluck('id_offre_emploi');
        $total_postulations += \App\Models\PostulationEmploi::whereIn('id_offre_emploi', $emploiIds)->count();
        
        $stageIds = $entreprise->offresStage()->pluck('id_offre_stage');
        $total_postulations += \App\Models\PostulationStage::whereIn('id_offre_stage', $stageIds)->count();
        
        $missionIds = $entreprise->missions()->pluck('id_mission');
        $total_postulations += \App\Models\PostulationFreelance::whereIn('id_mission', $missionIds)->count();

        // Recent activities (fetching latest 5 postulations)
        $recent_postulations = collect();

        $latestEmploi = \App\Models\PostulationEmploi::with(['candidat.user', 'offre'])
            ->whereIn('id_offre_emploi', $emploiIds)
            ->latest('date_postulation')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'type' => 'application',
                'title' => 'Candidature Emploi',
                'description' => "{$p->candidat->user->nom} a postule pour: " . ($p->offre->poste ?? $p->offre->titre ?? 'Poste sans titre'),
                'time' => $p->date_postulation->diffForHumans(),
                'raw_date' => $p->date_postulation
            ]);
        $recent_postulations = $recent_postulations->concat($latestEmploi);

        $latestStage = \App\Models\PostulationStage::with(['candidat.user', 'offre'])
            ->whereIn('id_offre_stage', $stageIds)
            ->latest('date_postulation')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'type' => 'application',
                'title' => 'Candidature Stage',
                'description' => "{$p->candidat->user->nom} a postule pour: " . ($p->offre->titre ?? $p->offre->poste ?? 'Poste non defini'),
                'time' => $p->date_postulation->diffForHumans(),
                'raw_date' => $p->date_postulation
            ]);
        $recent_postulations = $recent_postulations->concat($latestStage);

        $recent_activities = $recent_postulations->sortByDesc('raw_date')->take(5)->map(function($a) {
            unset($a['raw_date']);
            return $a;
        })->values()->all();

        return response()->json([
            'total_offres_emploi' => $total_offres_emploi,
            'total_offres_stage' => $total_offres_stage,
            'total_missions_freelance' => $total_missions_freelance,
            'total_postulations' => $total_postulations,
            'offres_ouvertes' => $offres_ouvertes ?? 0,
            'recent_activities' => $recent_activities ?? [],
        ]);
    }
}
