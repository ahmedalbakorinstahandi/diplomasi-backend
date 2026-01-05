<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateGlossaryTermRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'term' => 'sometimes|required|string|max:255',
            'definition' => 'sometimes|required|string',
            'language' => 'sometimes|nullable|string|max:10',
        ];
    }
}

