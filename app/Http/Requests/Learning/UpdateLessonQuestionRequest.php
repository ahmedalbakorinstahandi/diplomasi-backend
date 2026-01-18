<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateLessonQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'lesson_id' => 'sometimes|exists:lessons,id',
            'type' => 'sometimes|in:single_choice,multiple_choice,true_false,match',
            'question_text' => 'sometimes|string',
            'attached_path' => 'nullable|string|max:100',
            'explanation' => 'nullable|string',
            'score' => 'nullable|numeric|min:0',
            'options' => 'sometimes|array|min:1',
            'options.*.id' => 'nullable|exists:lesson_question_options,id',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.pair_key' => 'nullable|string|max:100',
            'options.*.is_correct' => 'nullable|boolean',
            'options.*.attached_path' => 'nullable|string|max:100',
        ];
    }
}
