<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

class UpdateNegotiationSituationRequest extends BaseFormRequest
{
    use ValidatesNegotiationResponseStyles;

    public function rules(): array
    {
        return [
            'negotiation_level_id' => 'sometimes|required|exists:negotiation_levels,id',
            'prompt_text' => 'sometimes|required|string',
            'prompt_context' => 'sometimes|nullable|string',
            'prompt_type' => 'sometimes|in:quote,scene',
            'insight' => 'sometimes|nullable|string',
            'is_free' => 'sometimes|boolean',
            'is_published' => 'sometimes|boolean',
            'responses' => 'sometimes|required|array|size:3',
            'responses.*.style' => 'required_with:responses|in:gentle,diplomatic,firm',
            'responses.*.response_text' => 'required_with:responses|string',
            'responses.*.explanation' => 'required_with:responses|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDistinctResponseStyles($validator);
    }
}
