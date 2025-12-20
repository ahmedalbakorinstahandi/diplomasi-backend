<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class UpdateSettingRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'key_name' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required|string',
            'type' => 'sometimes|nullable|string|max:50',
        ];
    }
}
