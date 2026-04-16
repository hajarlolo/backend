<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminModerateAccountRequest;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\SystemNotificationService;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly SystemNotificationService $systemNotificationService,
        private readonly UserActivityLogService $userActivityLogService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Statistics for overview cards
        $cards = [
            'students' => [
                'total' => User::whereIn('role', ['student', 'etudiant'])->count(),
                'pending' => User::whereIn('role', ['student', 'etudiant'])
                    ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))->count(),
            ],
            'companies' => [
                'total' => User::whereIn('role', ['company', 'entreprise'])->count(),
                'pending' => User::whereIn('role', ['company', 'entreprise'])
                    ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))->count(),
            ],
            'laureats' => [
                'total' => User::whereIn('role', ['laureat', 'lauriat'])->count(),
                'pending' => User::whereIn('role', ['laureat', 'lauriat'])
                    ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))->count(),
            ],
            'offers' => [
                'total' => \App\Models\OffreEmploi::count() + \App\Models\OffreStage::count() + \App\Models\MissionFreelance::count(),
                'pending' => \App\Models\OffreEmploi::where('statut', 'attente')->count() + \App\Models\OffreStage::where('statut', 'attente')->count() + \App\Models\MissionFreelance::where('statut', 'attente')->count(),
            ],
        ];

        // Growth graphs (User growth over 6 months)
        $graphs = [
            'user_growth' => $this->getUserGrowthData(),
            'offers' => [
                'emploi' => \App\Models\OffreEmploi::count(),
                'stage' => \App\Models\OffreStage::count(),
                'freelance' => \App\Models\MissionFreelance::count(),
            ]
        ];

        return response()->json([
            'cards' => $cards,
            'graphs' => $graphs
        ]);
    }

    public function companies(Request $request): JsonResponse
    {
        $query = \App\Models\Entreprise::with(['user', 'user.latestVerification']);
        
        if ($request->status) {
            $query->whereHas('user.latestVerification', fn($q) => $q->where('status', $request->status));
        }
        
        if ($request->search) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('ice', 'like', "%$s%")
                ->orWhere('secteur_activite', 'like', "%$s%")
                ->orWhereHas('user', fn($uq) => $uq->where('nom', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%")));
        }

        return response()->json($query->paginate(15));
    }

    public function candidates(Request $request): JsonResponse
    {
        $query = \App\Models\Candidat::with(['user', 'user.latestVerification']);

        if ($request->status) {
            $query->whereHas('user.latestVerification', fn($q) => $q->where('status', $request->status));
        }

        if ($request->type) {
            $query->whereHas('user', fn($q) => $q->where('role', $request->type === 'student' ? 'student' : 'lauriat'));
        }

        if ($request->search) {
            $s = $request->search;
            $query->whereHas('user', fn($q) => $q->where('nom', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%"));
        }

        return response()->json($query->paginate(15));
    }

    public function offers(Request $request): JsonResponse
    {
        $jobs = \App\Models\OffreEmploi::with('entreprise.user')->get()->map(fn($o) => [
            'id' => $o->id_offre_emploi,
            'title' => $o->poste,
            'company' => $o->entreprise->user->nom ?? 'N/A',
            'type' => 'emploi',
            'status' => $o->statut,
            'applicants' => $o->postulations()->count()
        ]);

        $stages = \App\Models\OffreStage::with('entreprise.user')->get()->map(fn($o) => [
            'id' => $o->id_offre_stage,
            'title' => $o->titre,
            'company' => $o->entreprise->user->nom ?? 'N/A',
            'type' => 'stage',
            'status' => $o->statut,
            'applicants' => $o->postulations()->count()
        ]);

        $missions = \App\Models\MissionFreelance::with('entreprise.user')->get()->map(fn($o) => [
            'id' => $o->id_mission,
            'title' => $o->titre,
            'company' => $o->entreprise->user->nom ?? 'N/A',
            'type' => 'freelance',
            'status' => $o->statut,
            'applicants' => $o->postulations()->count()
        ]);

        $all = $jobs->concat($stages)->concat($missions);

        return response()->json($all);
    }

    public function logs(Request $request): JsonResponse
    {
        $query = \App\Models\UserLog::with('user')->latest('created_at');

        if ($request->user_type) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->action_type) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->search) {
            $s = $request->search;
            $query->whereHas('user', fn($q) => $q->where('nom', 'like', "%$s%"));
        }

        return response()->json($query->paginate(20));
    }

    public function showDocument(User $user): JsonResponse
    {
        $verification = $user->latestVerification;

        if (!$verification || !$verification->verification_document) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        $path = $verification->verification_document;

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Fichier introuvable sur le disque.'], 404);
        }

        return response()->json([
            'user_id' => $user->id_user,
            'url' => Storage::disk('public')->url($path),
            'mime_type' => Storage::disk('public')->mimeType($path),
            'path' => $path,
        ]);
    }

    public function moderate(AdminModerateAccountRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $decision = $validated['decision']; // approved, rejected

        $status = ($decision === 'approved') ? 'approved' : 'rejected';

        $verification = $user->latestVerification;
        
        if (!$verification) {
            $verification = new UserVerification([
                'id_user' => $user->id_user,
            ]);
        }

        $verification->status = $status;
        $verification->status_note = $validated['status_note'] ?? null;
        $verification->save();

        if ($decision === 'approved') {
            $user->update(['is_active' => true]);
        }

        $this->systemNotificationService->notifyUserModerationResult($user, $decision, $verification->status_note);

        $actionType = ($decision === 'approved') ? 'accept_user' : 'reject_user';
        $this->userActivityLogService->log(
            auth()->user(), 
            $actionType, 
            'user', 
            $user->id_user, 
            "Admin {$decision} account for: {$user->email}. Note: " . ($verification->status_note ?? 'N/A')
        );

        return response()->json([
            'message' => "Compte {$decision} avec succes.",
            'user' => [
                'id' => $user->id_user,
                'email' => $user->email,
                'statut' => $user->normalizedVerificationStatus(),
            ]
        ]);
    }

    public function notifications(): JsonResponse
    {
        $pendingStudents = User::whereIn('role', ['student', 'etudiant'])
            ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))
            ->count();
            
        $pendingCompanies = User::whereIn('role', ['company', 'entreprise'])
            ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))
            ->count();
            
        $pendingLaureats = User::whereIn('role', ['laureat', 'lauriat'])
            ->whereHas('latestVerification', fn($q) => $q->where('status', 'pending_document'))
            ->count();

        $notifications = [];
        
        if ($pendingStudents > 0) {
            $notifications[] = [
                'id' => 'pending_students',
                'title' => 'Nouveaux étudiants',
                'message' => "{$pendingStudents} étudiants attendent une validation.",
                'type' => 'student',
                'count' => $pendingStudents,
                'link' => '/admin/candidates?status=pending_document&type=student'
            ];
        }
        
        if ($pendingCompanies > 0) {
            $notifications[] = [
                'id' => 'pending_companies',
                'title' => 'Nouvelles entreprises',
                'message' => "{$pendingCompanies} entreprises attendent une validation.",
                'type' => 'company',
                'count' => $pendingCompanies,
                'link' => '/admin/companies?status=pending_document'
            ];
        }

        if ($pendingLaureats > 0) {
            $notifications[] = [
                'id' => 'pending_laureats',
                'title' => 'Nouveaux lauréats',
                'message' => "{$pendingLaureats} lauréats attendent une validation.",
                'type' => 'laureat',
                'count' => $pendingLaureats,
                'link' => '/admin/candidates?status=pending_document&type=laureat'
            ];
        }

        return response()->json([
            'total' => $pendingStudents + $pendingCompanies + $pendingLaureats,
            'items' => $notifications
        ]);
    }

    private function calculateConversionRate(): float
    {
        $total = \App\Models\PostulationEmploi::count() + \App\Models\PostulationStage::count() + \App\Models\PostulationFreelance::count();
        if ($total === 0) return 0;
        
        $accepted = \App\Models\PostulationEmploi::whereIn('statut', ['accepte', 'accepté', 'acceptée'])->count() +
                    \App\Models\PostulationStage::whereIn('statut', ['accepte', 'accepté', 'acceptée'])->count() +
                    \App\Models\PostulationFreelance::whereIn('statut', ['accepte', 'accepté', 'acceptée'])->count();
                    
        return round(($accepted / $total) * 100, 1);
    }

    private function getUserGrowthData(): array
    {
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = User::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            $data[$month->format('M')] = $count;
        }
        return $data;
    }
}