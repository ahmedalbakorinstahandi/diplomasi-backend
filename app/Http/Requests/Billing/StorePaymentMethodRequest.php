<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class StorePaymentMethodRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string|max:191',
            'status' => 'nullable|string|in:active,inactive',
            'brand' => 'nullable|string|max:30',
            'last4' => 'nullable|string|max:4',
            'exp_month' => 'nullable|integer|min:1|max:12',
            'exp_year' => 'nullable|integer|min:2000|max:9999',
            'is_default' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ];
    }
}

