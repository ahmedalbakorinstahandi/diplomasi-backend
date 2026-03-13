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
            'caption' => 'sometimes|nullable|string|max:255',
            'is_featured' => 'sometimes|nullable|boolean',
            'features' => 'sometimes|nullable|array',
            'features.*' => 'sometimes|nullable|string',
            'icon_url' => 'sometimes|nullable|string|max:100',
            'ios_price' => 'sometimes|nullable|numeric|min:0',
            'ios_currency' => 'sometimes|nullable|string|max:10',
            'ios_product_id' => 'sometimes|nullable|string|max:191',
        ];
    }
}
