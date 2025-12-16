<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
