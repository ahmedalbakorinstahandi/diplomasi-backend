<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class RevokeCertificateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:500',
        ];
    }
}
