<?php

namespace App\Http\Requests\Scenarios;

use App\Http\Requests\BaseFormRequest;

class UpdateScenarioRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'sometimes|required|exists:levels,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|array',
            'is_published' => 'sometimes|nullable|boolean',
            'is_free' => 'sometimes|nullable|boolean',
            'start_question_id' => 'sometimes|nullable|exists:scenario_questions,id',
        ];
    }
}
