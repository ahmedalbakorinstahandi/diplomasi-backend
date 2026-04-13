<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateLevelCertificateConfigRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'certificate_template_config' => 'required|array',
        ];
    }
}
