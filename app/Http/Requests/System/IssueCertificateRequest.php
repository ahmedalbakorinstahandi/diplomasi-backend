<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class IssueCertificateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'level_id' => 'nullable|exists:levels,id',
        ];
    }
}
