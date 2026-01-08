<?php

namespace App\Http\Requests\Billing;

use App\Http\Requests\BaseFormRequest;

class ReOrderPlanRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|exists:plans,id', 
        ];
    }
}
