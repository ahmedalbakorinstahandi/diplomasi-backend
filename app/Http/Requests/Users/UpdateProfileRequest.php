<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\BaseFormRequest;

class UpdateProfileRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email',
            'phone' => 'sometimes|required|string|max:20',
            'address' => 'sometimes|nullable|string|max:255',
            'password' => 'sometimes|nullable|string|min:6|max:255',
            'current_password' => 'required_with:password|string',
        ];
    }
}
