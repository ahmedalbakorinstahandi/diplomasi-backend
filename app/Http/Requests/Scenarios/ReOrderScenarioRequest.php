<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class ReOrderScenarioRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'sort_order' => 'required|integer|min:1',
        ];
    }
}
