<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class ImportLessonQuestionsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'replace' => 'nullable|boolean',

            'questions' => 'required|array|min:1',

            'questions.*.type' => 'required|string|in:single_choice,multiple_choice,true_false,match',
            'questions.*.text' => 'required|string',
            'questions.*.score' => 'nullable|numeric',
            'questions.*.explanation' => 'nullable|string',

            // options for choice/true_false/multiple_choice/match
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.text' => 'required_with:questions.*.options|string',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.options.*.pair_key' => 'nullable|string|max:100',
        ];
    }
}

