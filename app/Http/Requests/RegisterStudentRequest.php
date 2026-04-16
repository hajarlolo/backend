<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $universityIds = array_map('intval', array_keys(config('universities', [])));

        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'universite_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'string', Rule::in(['student', 'laureat'])],
        ];
    }

    protected function prepareForValidation(): void
    {
    }

    public function attributes(): array
    {
        return [
            'universite_id' => 'universite / ecole',
        ];
    }
}