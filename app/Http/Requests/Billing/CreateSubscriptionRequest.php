<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CreateSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:active,inactive,cancelled,expired',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'stripe_subscription_id' => 'nullable|string|max:255',
            'auto_renew' => 'nullable|boolean',
        ];
    }
}
