<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class VerifyApplePurchaseRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'product_id' => 'required|string|max:191',
            'transaction_id' => 'required|string|max:191',
            'receipt' => 'required|string',
        ];
    }
}
