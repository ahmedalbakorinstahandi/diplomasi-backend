<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CreatePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'stripe_plan_id' => 'required|string|max:100|unique:plans,stripe_plan_id',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,semi_annual,annual',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'icon_url' => 'nullable|string|max:100',
        ];
    }
}
