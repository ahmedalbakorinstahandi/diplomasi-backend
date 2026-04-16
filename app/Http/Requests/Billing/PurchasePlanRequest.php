<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class PurchasePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'payment_method_id' => 'nullable|integer|exists:saved_payment_methods,id',
            'prepared_transaction_id' => 'nullable|integer|exists:payment_transactions,id',
        ];
    }
}

