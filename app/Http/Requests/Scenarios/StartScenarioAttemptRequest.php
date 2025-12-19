<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class StartScenarioAttemptRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'scenario_id' => 'required|exists:scenarios,id',
        ];
    }
}
