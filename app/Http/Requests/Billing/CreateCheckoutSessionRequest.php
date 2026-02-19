<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CreateCheckoutSessionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|integer|exists:plans,id',
            'source_type' => 'required|string|in:token',
            'source_token' => 'required|string|max:255',
            'callback_url' => 'required|url|max:500',
        ];
    }
}

