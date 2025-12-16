<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,deleted_at,NULL',
            'password' => 'required|string|min:6|max:255|confirmed',
            'avatar' => 'nullable|string|max:100',
            'phone' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل',
        ];
    }
}
