<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class CheckPhoneNumberRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
        ];
    }
}
