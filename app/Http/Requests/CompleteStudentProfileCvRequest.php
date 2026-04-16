<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteStudentProfileCvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cv_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}

