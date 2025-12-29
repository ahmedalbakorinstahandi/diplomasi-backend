<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class StartLessonAttemptRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            // lesson_id يأتي من route parameter، لا حاجة للتحقق هنا
        ];
    }
}

