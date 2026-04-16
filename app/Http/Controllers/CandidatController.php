<?php

namespace App\Http\Controllers;

use App\Models\Candidat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CandidatController extends Controller
{
    public function index()
    {
        return response()->json(Candidat::with(['user', 'universite', 'competences'])->get());
    }

    public function show($id)
    {
        $candidat = Candidat::with(['user', 'universite', 'experiences.competences', 'formations.universite', 'certificats', 'projets.technologies', 'competences'])->findOrFail($id);
        
        $user = $candidat->user;
        
        return response()->json([
            'personal_info' => [
                'nom' => $user->nom,
                'prenom' => $user->nom, // Fallback if no separate prenom
                'email' => $user->email,
                'telephone' => $candidat->telephone,
                'adresse' => $candidat->adresse,
                'date_naissance' => $candidat->date_naissance ? \Carbon\Carbon::parse($candidat->date_naissance)->format('Y-m-d') : null,
                'universite_id' => $candidat->universite_id,
                'academic_year' => $candidat->academic_year,
                'photo_profil' => $candidat->photo_profil ? \Illuminate\Support\Facades\Storage::url($candidat->photo_profil) : null,
                'lien_portfolio' => $candidat->lien_portfolio,
                'cv_url' => $candidat->cv_url ? \Illuminate\Support\Facades\Storage::url($candidat->cv_url) : null,
            ],
            'competences' => $candidat->competences->pluck('nom'),
            'formations' => $candidat->formations->map(fn($f) => [
                ...$f->toArray(),
                'universite_label' => $f->universite->nom ?? null
            ]),
            'experiences' => $candidat->experiences->map(fn($e) => [
                'id' => $e->id_experience,
                'type' => $e->type,
                'titre' => $e->titre,
                'entreprise_nom' => $e->entreprise_nom,
                'description' => $e->description,
                'date_debut' => $e->date_debut->format('Y-m-d'),
                'date_fin' => $e->date_fin ? $e->date_fin->format('Y-m-d') : null,
                'competences' => $e->competences->pluck('nom'),
            ]),
            'projets' => $candidat->projets->map(fn($p) => [
                ...$p->toArray(),
                'technologies' => $p->technologies->pluck('nom'),
                'image_apercu_url' => $p->image_apercu ? \Illuminate\Support\Facades\Storage::url($p->image_apercu) : null,
            ]),
            'certificats' => $candidat->certificats,
        ]);
    }

    public function update(Request $request, $id)
    {
        $candidat = Candidat::findOrFail($id);
        
        // Authorization check: Only the owner or admin can update
        if (auth()->id() !== $candidat->id_user && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'universite_id' => 'sometimes|exists:universites,id_universite',
            'date_naissance' => 'sometimes|date',
            'telephone' => 'sometimes|string|max:30',
            'adresse' => 'sometimes|string|max:255',
            'lien_portfolio' => 'sometimes|url|max:255',
            'profile_mode' => 'sometimes|in:manual,cv',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $candidat->update($request->all());
        
        if ($request->has('competences')) {
            $candidat->competences()->sync($request->competences);
        }

        return response()->json($candidat->load(['user', 'universite', 'competences']));
    }
}
