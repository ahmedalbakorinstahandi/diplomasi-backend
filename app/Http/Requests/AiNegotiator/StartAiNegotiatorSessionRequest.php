<?php

namespace App\Http\Requests\AiNegotiator;

use App\Http\Requests\BaseFormRequest;

class StartAiNegotiatorSessionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'session_type' => 'sometimes|in:practice',
            'difficulty' => 'sometimes|in:realistic',
            'training_mode' => 'sometimes|in:realistic',
            'situation_type' => 'sometimes|string|max:50|nullable',
        ];
    }
}
