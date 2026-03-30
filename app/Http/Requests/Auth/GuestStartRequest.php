<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class GuestStartRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'device_token' => 'nullable|string|max:500',
        ];
    }
}
