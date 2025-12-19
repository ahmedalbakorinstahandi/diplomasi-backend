<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class UpdateSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'sometimes|required|exists:users,id',
            'plan_id' => 'sometimes|required|exists:plans,id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'status' => 'sometimes|nullable|in:active,inactive,cancelled,expired',
            'price' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|nullable|string|max:3',
            'stripe_subscription_id' => 'sometimes|nullable|string|max:255',
            'auto_renew' => 'sometimes|nullable|boolean',
        ];
    }
}
