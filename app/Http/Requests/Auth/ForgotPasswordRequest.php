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
            'purpose' => 'sometimes|string|in:password_reset,account_activation',
        ];
    }
}
