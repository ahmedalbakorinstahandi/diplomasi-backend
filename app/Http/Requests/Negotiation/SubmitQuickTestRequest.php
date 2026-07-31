<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;
use App\Services\NegotiationQuizService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitQuickTestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'size:' . NegotiationQuizService::QUICK_TEST_QUESTION_COUNT],
            'answers.*.asked_style' => [
                'required',
                'string',
                Rule::in(NegotiationQuizService::STYLES),
            ],
            'answers.*.selected_response_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $answers = $this->input('answers', []);
            if (!is_array($answers)) {
                return;
            }

            $styles = array_column($answers, 'asked_style');
            if (count($styles) !== count(array_unique($styles))) {
                $validator->errors()->add('answers', 'Each asked_style may appear only once.');
            }

            foreach (NegotiationQuizService::STYLES as $required) {
                if (!in_array($required, $styles, true)) {
                    $validator->errors()->add('answers', "Missing asked_style: {$required}");
                }
            }
        });
    }
}
