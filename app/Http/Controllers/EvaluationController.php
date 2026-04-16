<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EvaluationController extends Controller
{
    public function index()
    {
        return response()->json(Evaluation::with(['entreprise', 'candidat.user'])->get());
    }

    public function store(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
        $isCompany = (strtolower($user->role) === 'company' || strtolower($user->role) === 'entreprise');
        $isLaureat = (strtolower($user->role) === 'laureat' || strtolower($user->role) === 'lauriat');
        \Illuminate\Support\Facades\Log::info('Evaluation attempt', [
            'user_role' => $user->role,
            'isCompany' => $isCompany,
            'isLaureat' => $isLaureat,
            'payload' => $request->all(),
        ]);

        $evaluatorRole = $isCompany ? 'company' : 'student';

        // Business Rule: Student cannot evaluate company, only Laureat can.
        if (!$isCompany && !$isLaureat) {
            \Illuminate\Support\Facades\Log::warning('Evaluation blocked: Not Company or Laureat');
            return response()->json(['message' => 'Seuls les lauréats et entreprises peuvent évaluer.'], 403);
        }
        $validator = Validator::make($request->all(), [
            'id_entreprise' => $evaluatorRole === 'student' ? 'required|exists:entreprises,id_entreprise' : 'nullable',
            'id_candidat' => $evaluatorRole === 'company' ? 'required|exists:candidats,id_candidat' : 'nullable',
            'note' => 'required|numeric|min:0|max:5',
            'commentaire' => 'nullable|string',
            'statut_mission' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Evaluation validation failed', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Validation échouée.',
                'errors' => $validator->errors(),
                'received_payload' => $request->all()
            ], 422);
        }

        $id_entreprise = $evaluatorRole === 'company' 
            ? ($user->entreprise?->id_entreprise ?? $request->id_entreprise) 
            : $request->id_entreprise;

        $candidat = $user->candidat ?? $user->etudiant;
        $id_candidat = $evaluatorRole === 'student' 
            ? ($candidat?->id_candidat ?? $request->id_candidat) 
            : $request->id_candidat;

        if (!$id_entreprise || !$id_candidat) {
            \Illuminate\Support\Facades\Log::error('Evaluation ID missing', ['id_entreprise'=>$id_entreprise, 'id_candidat'=>$id_candidat]);
            return response()->json([
                'message' => 'Le profil doit être complété pour pouvoir évaluer (ID manquant).',
                'id_entreprise' => $id_entreprise,
                'id_candidat' => $id_candidat,
                'evaluator_role' => $evaluatorRole
            ], 422);
        }

        try {
            \App\Models\Evaluation::insert([
                'id_entreprise' => $id_entreprise,
                'id_candidat' => $id_candidat,
                'evaluator_role' => $evaluatorRole,
                'note' => $request->note,
                'commentaire' => $request->commentaire,
                'date_evaluation' => now()->format('Y-m-d H:i:s'),
            ]);
            
            $evaluation = \App\Models\Evaluation::where('id_entreprise', $id_entreprise)
                ->where('id_candidat', $id_candidat)
                ->where('evaluator_role', $evaluatorRole)
                ->orderByDesc('date_evaluation')
                ->first();

            return response()->json($evaluation, 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Evaluation SQL error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Erreur de base de données.', 'error' => $e->getMessage()], 500);
        }
    }

    public function showByCandidat($id_candidat)
    {
        // Get evaluations where candidate is the TARGET (evaluated by company)
        $evaluations = Evaluation::with(['entreprise.user'])
            ->where('id_candidat', $id_candidat)
            ->where('evaluator_role', 'company')
            ->get();
        return response()->json($evaluations);
    }

    public function showByEntreprise($id_entreprise)
    {
        // Get evaluations where enterprise is the TARGET (evaluated by student)
        $evaluations = Evaluation::with(['candidat.user'])
            ->where('id_entreprise', $id_entreprise)
            ->where('evaluator_role', 'student')
            ->get();
        return response()->json($evaluations);
    }

    public function showMyCandidatEvaluations(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) return response()->json(['message' => 'Non authentifié'], 401);
        
        $candidat = $user->candidat ?? $user->etudiant; // Check both synonyms
        if (!$candidat) {
            return response()->json(['message' => 'Candidat non trouvé.'], 404);
        }
        return $this->showByCandidat($candidat->id_candidat);
    }

    public function showMyEntrepriseEvaluations(Request $request)
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) return response()->json(['message' => 'Non authentifié'], 401);
        
        if (!$user->entreprise) {
            return response()->json(['message' => 'Entreprise non trouvée.'], 404);
        }
        return $this->showByEntreprise($user->entreprise->id_entreprise);
    }
}
