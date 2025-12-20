<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

class UpdateManySettingsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            '*' => 'required',
            '*.value' => 'nullable',
            '*.type' => 'nullable|in:int,float,text,long_text,json,bool,datetime,html',
        ];
    }
}
