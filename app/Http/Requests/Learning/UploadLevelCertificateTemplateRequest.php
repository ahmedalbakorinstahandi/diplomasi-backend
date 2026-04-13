<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UploadLevelCertificateTemplateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $maxKb = (int) config('certificate_template.max_upload_kb', 8192);

        return [
            'template' => [
                'required',
                'file',
                'image',
                'mimes:png,jpg,jpeg',
                'max:' . $maxKb,
            ],
        ];
    }
}
