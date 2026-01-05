<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class CreateGlossaryTermRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'term' => 'required|string|max:255',
            'definition' => 'required|string',
            'language' => 'nullable|string|max:10',
        ];
    }
}

