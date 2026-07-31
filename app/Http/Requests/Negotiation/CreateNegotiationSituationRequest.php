<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

class CreateNegotiationSituationRequest extends BaseFormRequest
{
    use ValidatesNegotiationResponseStyles;

    public function rules(): array
    {
        return [
            'negotiation_level_id' => 'required|exists:negotiation_levels,id',
            'prompt_text' => 'required|string',
            'prompt_context' => 'nullable|string',
            'prompt_type' => 'required|in:quote,scene',
            'insight' => 'nullable|string',
            'is_free' => 'required|boolean',
            'is_published' => 'sometimes|boolean',
            'responses' => 'required|array|size:3',
            'responses.*.style' => 'required|in:gentle,diplomatic,firm',
            'responses.*.response_text' => 'required|string',
            'responses.*.explanation' => 'required|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDistinctResponseStyles($validator);
    }
}
