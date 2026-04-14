<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class ReOrderReengagementReminderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'new_order_index' => 'required|integer|min:1',
        ];
    }
}
