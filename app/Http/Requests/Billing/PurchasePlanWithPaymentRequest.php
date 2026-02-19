<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class PurchasePlanWithPaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'gateway_payment_id' => 'required|string|max:191',
            'token' => 'nullable|string|max:191',
            'brand' => 'nullable|string|max:30',
            'last4' => 'nullable|string|max:4',
            'exp_month' => 'nullable|integer|min:1|max:12',
            'exp_year' => 'nullable|integer|min:2000|max:9999',
            'meta' => 'nullable|array',
        ];
    }
}

