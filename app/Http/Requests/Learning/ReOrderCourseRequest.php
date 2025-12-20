<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class ReOrderCourseRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|exists:courses,id', 
        ];
    }
}
