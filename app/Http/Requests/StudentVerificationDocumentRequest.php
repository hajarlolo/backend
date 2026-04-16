<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentVerificationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_document' => ['required', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:10240'],
        ];
    }
}

