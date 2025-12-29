<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class SubmitLessonAnswerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'question_id' => 'required|exists:lesson_questions,id',
            'option_id' => 'nullable|exists:lesson_question_options,id',
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'exists:lesson_question_options,id',
            'matches' => 'nullable|array',
            'matches.*.left_option_id' => 'required_with:matches|exists:lesson_question_options,id',
            'matches.*.right_option_id' => 'required_with:matches|exists:lesson_question_options,id',
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.required' => 'messages.answer.question_id_required',
            'question_id.exists' => 'messages.answer.question_not_found',
            'option_id.exists' => 'messages.answer.invalid_option',
            'option_ids.array' => 'messages.answer.option_ids_must_be_array',
            'option_ids.*.exists' => 'messages.answer.invalid_option',
            'matches.array' => 'messages.answer.matches_must_be_array',
            'matches.*.left_option_id.required_with' => 'messages.answer.left_option_id_required',
            'matches.*.right_option_id.required_with' => 'messages.answer.right_option_id_required',
            'matches.*.left_option_id.exists' => 'messages.answer.invalid_option',
            'matches.*.right_option_id.exists' => 'messages.answer.invalid_option',
        ];
    }
}

