<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class MarkNotificationAsReadRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // No validation needed, just marking as read
        ];
    }
}
