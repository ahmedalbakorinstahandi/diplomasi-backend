<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class CreateScenarioQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'scenario_id' => 'required|exists:scenarios,id',
            'code' => 'required|string|max:20',
            'type' => 'required|in:single_choice',
            'question_text' => 'required|string',
            'attached_path' => 'nullable|string|max:100',
            'explanation' => 'nullable|string',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.next_question_id' => 'nullable|exists:scenario_questions,id',
            'options.*.next_question_code' => 'nullable|string|max:20',
            'options.*.feedback_text' => 'nullable|string',
            'options.*.attached_path' => 'nullable|string|max:100',
        ];
    }
}
