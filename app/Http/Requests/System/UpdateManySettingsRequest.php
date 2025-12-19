<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class UpdateManySettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.*.key_name' => 'required|string|max:255',
            'settings.*.value' => 'required|string',
        ];
    }
}
