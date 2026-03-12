<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class ImportScenarioContentRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'replace' => 'nullable|boolean',
            'screens' => 'required|array|min:1',
            'screens.*.question_text' => 'required|string',
            'screens.*.explanation' => 'nullable|string',
            'screens.*.options' => 'required|array|min:1',
            'screens.*.options.*.option_text' => 'required|string',
            'screens.*.options.*.feedback_text' => 'nullable|string',
            'screens.*.options.*.next' => 'nullable|string|max:20',
        ];
    }
}
