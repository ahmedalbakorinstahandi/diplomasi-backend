<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class CreateLessonQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'lesson_id' => 'required|exists:lessons,id',
            'type' => 'required|in:single_choice,multiple_choice,true_false,match',
            'question_text' => 'required|string',
            'attached_path' => 'nullable|string|max:100',
            'explanation' => 'nullable|string',
            'score' => 'nullable|numeric|min:0',
            'options' => 'required|array|min:1',
            'options.*.option_text' => 'required|string',
            'options.*.pair_key' => 'nullable|string|max:100',
            'options.*.is_correct' => 'nullable|boolean',
            'options.*.attached_path' => 'nullable|string|max:100',
        ];
    }
}
