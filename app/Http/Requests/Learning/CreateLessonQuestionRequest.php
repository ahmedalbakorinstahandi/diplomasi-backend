<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('type') !== 'match') {
                return;
            }
            $options = $this->input('options', []);
            $pairKeys = collect($options)->pluck('pair_key')->filter(fn ($v) => $v !== null && $v !== '');
            $counts = $pairKeys->countBy();
            $overTwo = $counts->filter(fn ($c) => $c > 2);
            if ($overTwo->isNotEmpty()) {
                $validator->errors()->add(
                    'options',
                    __('Each pair_key in a match question must appear at most twice.')
                );
            }
        });
    }
}
