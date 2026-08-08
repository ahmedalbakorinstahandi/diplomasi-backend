<?php

namespace App\Http\Requests\AiNegotiator;

use App\Http\Requests\BaseFormRequest;

class SubmitAiNegotiatorMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'required|string|min:1|max:5000',
        ];
    }
}
