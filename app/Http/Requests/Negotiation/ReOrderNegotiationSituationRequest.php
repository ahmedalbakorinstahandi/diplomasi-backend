<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;

class ReOrderNegotiationSituationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|integer|min:1',
        ];
    }
}
