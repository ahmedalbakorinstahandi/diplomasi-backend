<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\BaseFormRequest;

class CreateUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'status' => 'nullable|in:active,banned',
        ];
    }
}
