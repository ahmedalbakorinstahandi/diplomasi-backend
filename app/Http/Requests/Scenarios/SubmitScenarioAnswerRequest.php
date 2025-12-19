<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class SubmitScenarioAnswerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'attempt_id' => 'required|exists:user_scenario_attempts,id',
            'question_id' => 'required|exists:scenario_questions,id',
            'option_id' => 'nullable|exists:scenario_question_options,id',
            'answer_text' => 'nullable|string',
        ];
    }
}
