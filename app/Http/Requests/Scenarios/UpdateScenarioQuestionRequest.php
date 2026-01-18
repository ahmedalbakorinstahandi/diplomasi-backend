<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class UpdateScenarioQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'scenario_id' => 'sometimes|exists:scenarios,id',
            'code' => 'sometimes|string|max:20',
            'type' => 'sometimes|in:single_choice,true_false',
            'question_text' => 'sometimes|string',
            'attached_path' => 'nullable|string|max:100',
            'explanation' => 'nullable|string',
            'options' => 'sometimes|array|min:2',
            'options.*.id' => 'nullable|exists:scenario_question_options,id',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.next_question_id' => 'nullable|exists:scenario_questions,id',
            'options.*.attached_path' => 'nullable|string|max:100',
        ];
    }
}
