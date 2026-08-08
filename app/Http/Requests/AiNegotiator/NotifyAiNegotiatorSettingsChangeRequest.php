<?php

namespace App\Http\Requests\AiNegotiator;

use App\Http\Requests\BaseFormRequest;

class NotifyAiNegotiatorSettingsChangeRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'message' => 'required|string|min:5|max:500',
        ];
    }
}
