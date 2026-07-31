<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;

class ReOrderNegotiationLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|integer|min:1',
        ];
    }
}
