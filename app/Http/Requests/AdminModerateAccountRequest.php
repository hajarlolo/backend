<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminModerateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approuve', 'refuse', 'approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

