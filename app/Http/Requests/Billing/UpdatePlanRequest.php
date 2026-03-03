<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class UpdatePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'price' => 'sometimes|required|numeric|min:0',
            'interval' => 'sometimes|required|in:monthly,quarterly,semi_annual,annual',
            'description' => 'sometimes|nullable|string',
            'features' => 'sometimes|nullable|array',
            'features.*' => 'sometimes|nullable|string',
            'icon_url' => 'sometimes|nullable|string|max:100',
        ];
    }
}
