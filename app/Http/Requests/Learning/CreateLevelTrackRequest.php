<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class CreateLevelTrackRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'required|exists:levels,id',
            'trackable_id' => 'required|integer',
            'trackable_type' => 'required|string|in:App\\Models\\Learning\\Lesson,App\\Models\\Scenarios\\Scenario',
        ];
    }
}

