<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class ReOrderLessonQuestionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|integer|min:1',
        ];
    }
}
