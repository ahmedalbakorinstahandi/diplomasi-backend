<?php

namespace App\Http\Requests\Learning;

use App\Http\Requests\BaseFormRequest;

class UpdateLevelTrackRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'level_id' => 'sometimes|required|exists:levels,id',
            'trackable_id' => 'sometimes|required|integer',
            'trackable_type' => 'sometimes|required|string|in:App\\Models\\Learning\\Lesson,App\\Models\\Scenarios\\Scenario',
        ];
    }
}

