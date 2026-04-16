<?php

namespace App\Http\Controllers;

use App\Models\OffreEmploi;
use App\Jobs\CalculateOfferMatchesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OffreEmploiController extends Controller
{
    use \App\Traits\Loggable;

    public function index()
    {
        return response()->json(OffreEmploi::with(['entreprise', 'competences'])->get());
    }

    public function store(Request $request)
    {
        // Authorization check: Only company or admin can create job offers
        if (!in_array(auth()->user()->role, ['company', 'entreprise', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->all();
        // Auto-inject company ID if the user is a company owner
        if (auth()->user()->role === 'company' || auth()->user()->role === 'entreprise') {
            $data['id_entreprise'] = auth()->user()->entreprise->id_entreprise;
        }

        $validator = Validator::make($data, [
            'id_entreprise' => 'required|exists:entreprises,id_entreprise',
            'poste' => 'required|string|max:180',
            'description' => 'required|string',
            'document_requise' => 'nullable|string',
            'experience_requise' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'salaire' => 'nullable|numeric|min:0',
            'statut' => 'nullable|in:publie,ferme',
            'competences' => 'nullable|array',
            'competences.*' => 'exists:competences,id_competence',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $offre = OffreEmploi::create($data);
        if (isset($data['competences'])) {
            $offre->competences()->attach($data['competences']);
        }

        // Logging the creation action
        $this->logActivity('create_offer', 'offer', $offre->id_offre_emploi, "Created job offer: {$offre->poste}");

        CalculateOfferMatchesJob::dispatch($offre, 'emploi');

        return response()->json($offre->load(['entreprise', 'competences']), 201);
    }

    public function show($id)
    {
        $offre = OffreEmploi::with(['entreprise', 'competences', 'postulations.candidat.user'])->findOrFail($id);
        return response()->json($offre);
    }

    public function update(Request $request, $id)
    {
        $offre = OffreEmploi::findOrFail($id);
        
        // Authorization check: Only the owner or admin can update
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $offre->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'poste' => 'sometimes|string|max:180',
            'description' => 'sometimes|string',
            'document_requise' => 'sometimes|string',
            'statut' => 'sometimes|in:publie,ferme',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $offre->update($request->all());

        // Logging the update action
        if ($request->has('statut')) {
            $action = $request->statut === 'ferme' ? 'close_offer' : 'update_profile'; // Using update_profile as a fallback or generic update if needed
            $this->logActivity($action, 'offer', $offre->id_offre_emploi, "Offer status updated to {$request->statut} for: {$offre->poste}");
        }

        if ($request->has('competences')) {
            $offre->competences()->sync($request->competences);
        }

        return response()->json($offre->load(['entreprise', 'competences']));
    }

    public function destroy($id)
    {
        $offre = OffreEmploi::findOrFail($id);
        
        // Authorization check: Only the owner or admin can delete
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $offre->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $offre->delete();
        return response()->json(null, 204);
    }
}
