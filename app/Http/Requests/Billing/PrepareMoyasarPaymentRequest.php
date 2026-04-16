<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class PrepareMoyasarPaymentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:plan_purchase,card_verification',
            'plan_id' => 'required_if:type,plan_purchase|integer|exists:plans,id',
        ];
    }
}

