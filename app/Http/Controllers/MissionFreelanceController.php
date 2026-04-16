<?php

namespace App\Http\Controllers;

use App\Models\MissionFreelance;
use App\Jobs\CalculateOfferMatchesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MissionFreelanceController extends Controller
{
    public function index()
    {
        return response()->json(MissionFreelance::with(['entreprise', 'competences'])->get());
    }

    public function store(Request $request)
    {
        // Authorization check: Only company or admin can create missions
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
            'budget' => 'nullable|numeric|min:0',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'duree_days' => 'nullable|integer|min:1',
            'statut' => 'nullable|in:publie,ferme',
            'competences' => 'nullable|array',
            'competences.*' => 'exists:competences,id_competence',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $mission = MissionFreelance::create($data);
        if (isset($data['competences'])) {
            $mission->competences()->attach($data['competences']);
        }

        CalculateOfferMatchesJob::dispatch($mission, 'freelance');

        return response()->json($mission->load(['entreprise', 'competences']), 201);
    }

    public function show($id)
    {
        $mission = MissionFreelance::with(['entreprise', 'competences', 'postulations.candidat.user'])->findOrFail($id);
        return response()->json($mission);
    }

    public function update(Request $request, $id)
    {
        $mission = MissionFreelance::findOrFail($id);
        
        // Authorization check: Only the owner or admin can update
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $mission->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:180',
            'description' => 'sometimes|string',
            'budget' => 'sometimes|numeric|min:0',
            'statut' => 'sometimes|in:publie,ferme',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $mission->update($request->all());

        if ($request->has('competences')) {
            $mission->competences()->sync($request->competences);
        }

        return response()->json($mission->load(['entreprise', 'competences']));
    }

    public function destroy($id)
    {
        $mission = MissionFreelance::findOrFail($id);
        
        // Authorization check: Only the owner or admin can delete
        if (auth()->user()->role !== 'admin' && (auth()->user()->entreprise && auth()->user()->entreprise->id_entreprise !== $mission->id_entreprise)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $mission->delete();
        return response()->json(null, 204);
    }
}
