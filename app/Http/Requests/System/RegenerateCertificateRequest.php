<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rendered_name' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
        ];
    }
}
