<?php

namespace App\Http\Controllers;

use App\Models\OffreStage;
use App\Jobs\CalculateOfferMatchesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OffreStageController extends Controller
{
    public function index()
    {
        return response()->json(OffreStage::with(['entreprise', 'competences'])->get());
    }

    public function store(Request $request)
    {
        // Authorization check: Only company or admin can create internship offers
        if (!in_array(auth()->user()->role, ['company', 'entreprise', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->all();
        if (auth()->user()->role === 'company' || auth()->user()->role === 'entreprise') {
            $data['id_entreprise'] = auth()->user()->entreprise->id_entreprise;
        }

        $validator = Validator::make($data, [
            'id_entreprise' => 'required|exists:entreprises,id_entreprise',
            'titre' => 'required|string|max:180',
            'description' => 'required|string',
            'document_requise' => 'nullable|string',
            'duree_days' => 'nullable|integer|min:1',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'remuneration' => 'nullable|numeric|min:0',
            'statut' => 'nullable|in:publie,ferme',
            'competences' => 'nullable|array',
            'competences.*' => 'exists:competences,id_competence',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $offre = OffreStage::create($data);
        if (isset($data['competences'])) {
            $offre->competences()->attach($data['competences']);
        }

        CalculateOfferMatchesJob::dispatch($offre, 'stage');

        return response()->json($offre->load(['entreprise', 'competences']), 201);
    }

    public function show($id)
    {
        $offre = OffreStage::with(['entreprise', 'competences', 'postulations.candidat.user'])->findOrFail($id);
        return response()->json($offre);
    }

    public function update(Request $request, $id)
    {
        $offre = OffreStage::findOrFail($id);
        
        // Authorization check: Only the owner or admin can update
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $offre->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:180',
            'description' => 'sometimes|string',
            'statut' => 'sometimes|in:publie,ferme',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $offre->update($request->all());

        if ($request->has('competences')) {
            $offre->competences()->sync($request->competences);
        }

        return response()->json($offre->load(['entreprise', 'competences']));
    }

    public function destroy($id)
    {
        $offre = OffreStage::findOrFail($id);
        
        // Authorization check: Only the owner or admin can delete
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $offre->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $offre->delete();
        return response()->json(null, 204);
    }
}
