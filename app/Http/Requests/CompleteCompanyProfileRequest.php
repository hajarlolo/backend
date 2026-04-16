<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:100', 'max:300'],
            'secteur_activite' => ['required', 'string', 'max:120'],
            'site_web' => ['required', 'url', 'max:255'],
            'taille' => ['required', Rule::in(['1-10', '10-50', '50-200', '+200'])],
            'telephone' => ['required', 'string', 'max:30'],
            'localisation' => ['required', 'string', 'max:255'],
            'email_professionnel' => ['required', 'email', 'max:255', 'unique:entreprises,email_professionnel,' . optional($this->user()?->entreprise)->id_entreprise . ',id_entreprise'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];
    }
}

