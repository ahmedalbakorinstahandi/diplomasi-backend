<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class ReOrderArticleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|exists:articles,id',
        ];
    }
}
