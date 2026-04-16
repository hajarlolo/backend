<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteStudentProfileManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_naissance' => ['required', 'date', 'before:today'],
            'universite_id' => ['required', 'integer', 'exists:universites,id_universite'],
            'telephone' => ['required', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'lien_portfolio' => ['nullable', 'url', 'max:255'],
            'photo_profil' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
            'competences' => ['nullable', 'array'],
            'competences.*' => ['string', 'max:100'],
            'formations' => ['nullable', 'array'],
            'formations.*.diplome' => ['required_with:formations', 'string', 'max:150'],
            'formations.*.filiere' => ['required_with:formations', 'string', 'max:150'],
            'formations.*.etablissement' => ['required_with:formations', 'string', 'max:255'],
            'formations.*.niveau' => ['required_with:formations', 'string', 'in:bac,bac+2,licence,master,doctorat,autre'],
            'formations.*.date_debut' => ['required_with:formations', 'date'],
            'formations.*.date_fin' => ['nullable', 'date'],
            'formations.*.en_cours' => ['nullable', 'boolean'],
            'experiences' => ['nullable', 'array'],
            'experiences.*.type' => ['required_with:experiences', Rule::in(['stage', 'emploi', 'freelance'])],
            'experiences.*.titre' => ['required_with:experiences', 'string', 'max:180'],
            'experiences.*.entreprise_nom' => ['nullable', 'string', 'max:150'],
            'experiences.*.description' => ['nullable', 'string'],
            'experiences.*.date_debut' => ['required_with:experiences', 'date'],
            'experiences.*.date_fin' => ['nullable', 'date'],
            'experiences.*.en_cours' => ['nullable', 'boolean'],
            'projets' => ['nullable', 'array'],
            'projets.*.titre' => ['required_with:projets', 'string', 'max:180'],
            'projets.*.description' => ['nullable', 'string'],
            'projets.*.technologies' => ['nullable', 'array'],
            'projets.*.technologies.*' => ['string', 'max:255'],
            'projets.*.lien_demo' => ['nullable', 'url'],
            'projets.*.lien_code' => ['nullable', 'url'],
            'projets.*.date' => ['nullable', 'date'],
            'certificats' => ['nullable', 'array'],
            'certificats.*.titre' => ['required_with:certificats', 'string', 'max:180'],
            'certificats.*.organisme' => ['required_with:certificats', 'string', 'max:180'],
            'certificats.*.date_obtention' => ['required_with:certificats', 'date'],
        ];
    }
}
