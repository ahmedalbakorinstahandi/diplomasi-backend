<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;
use App\Services\NegotiationQuizService;
use Illuminate\Validation\Rule;

class SubmitFinalTestRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'size:' . NegotiationQuizService::FINAL_TEST_QUESTION_COUNT],
            'answers.*.situation_id' => ['required', 'integer'],
            'answers.*.asked_style' => [
                'required',
                'string',
                Rule::in(NegotiationQuizService::STYLES),
            ],
            'answers.*.selected_response_id' => ['nullable', 'integer'],
        ];
    }
}
