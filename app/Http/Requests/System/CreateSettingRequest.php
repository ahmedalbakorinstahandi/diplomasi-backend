<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class CreateSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'key_name' => 'required|string|max:255|unique:settings,key_name',
            'value' => 'required|string',
            'type' => 'nullable|string|max:50',
            'is_settings' => 'nullable|boolean',
        ];
    }
}
