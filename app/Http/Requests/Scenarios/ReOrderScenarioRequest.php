<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class ReOrderScenarioRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|exists:scenarios,id',
        ];
    }
}
