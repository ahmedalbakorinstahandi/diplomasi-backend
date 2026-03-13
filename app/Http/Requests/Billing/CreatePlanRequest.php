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
            'caption' => 'nullable|string|max:255',
            'is_featured' => 'nullable|boolean',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'icon_url' => 'nullable|string|max:100',
            'ios_price' => 'nullable|numeric|min:0',
            'ios_currency' => 'nullable|string|max:10',
            'ios_product_id' => 'nullable|string|max:191',
        ];
    }
}
