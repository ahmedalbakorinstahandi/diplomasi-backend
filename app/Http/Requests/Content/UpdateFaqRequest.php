<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class UpdateFaqRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'question' => 'sometimes|required|string',
            'answer' => 'sometimes|required|string',
        ];
    }
}
