<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class ReOrderLevelRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'sort_order' => 'required|integer|min:1',
        ];
    }
}
