<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class ReOrderPodcastRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => ['required', 'integer', 'min:1'],
        ];
    }
}
