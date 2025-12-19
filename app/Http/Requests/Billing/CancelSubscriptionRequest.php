<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class CancelSubscriptionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }
}
