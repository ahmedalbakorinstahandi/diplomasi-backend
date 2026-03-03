<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CreatePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,quarterly,semi_annual,annual',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'icon_url' => 'nullable|string|max:100',
        ];
    }
}
