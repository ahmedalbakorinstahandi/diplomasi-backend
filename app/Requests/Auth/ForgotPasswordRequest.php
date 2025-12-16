<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'يجب أن يكون البريد الإلكتروني صالح',
        ];
    }
}
