<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;

class CreateNegotiationLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'how_to_study' => 'nullable|string',
            'is_published' => 'sometimes|boolean',
        ];
    }
}
