<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class CreateNotificationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'nullable|string|max:50',
            'data' => 'nullable|array',
        ];
    }
}
