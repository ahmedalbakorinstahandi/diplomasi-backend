<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class CreateScenarioRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'required|exists:levels,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|array',
            'is_published' => 'nullable|boolean',
            'is_free' => 'nullable|boolean',
            'start_question_id' => 'nullable|exists:scenario_questions,id',
        ];
    }
}
