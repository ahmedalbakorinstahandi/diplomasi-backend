<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class CreateFaqRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'question' => 'required|string',
            'answer' => 'required|string',
        ];
    }
}
