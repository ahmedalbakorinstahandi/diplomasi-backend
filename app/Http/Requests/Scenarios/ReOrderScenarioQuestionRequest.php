<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class ReOrderScenarioQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|integer|min:1',
        ];
    }
}
