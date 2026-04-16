<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([
                'en_attente',
                'approuve',
                'refuse',
                'pending_email',
                'pending_document',
                'pending_admin',
                'approved',
                'rejected',
            ])],
            'role' => ['nullable', Rule::in(['student', 'company'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}

