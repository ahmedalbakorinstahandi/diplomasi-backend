<?php

namespace App\Http\Requests\Negotiation;

use App\Http\Requests\BaseFormRequest;

class UpdateNegotiationLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'how_to_study' => 'sometimes|nullable|string',
            'is_published' => 'sometimes|boolean',
        ];
    }
}
