<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => ['required', 'email'],
            'password' => 'required|string|min:6|max:255|confirmed',
            'avatar' => 'nullable|string|max:100',
            'phone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'phone')->whereNull('deleted_at'),
            ],
        ];
    }
}
