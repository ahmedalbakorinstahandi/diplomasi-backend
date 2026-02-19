<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class PurchasePlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
        ];
    }
}

