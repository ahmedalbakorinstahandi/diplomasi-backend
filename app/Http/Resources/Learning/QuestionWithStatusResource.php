<?php

namespace App\Http\Resources\Learning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionWithStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'question_text' => $this->question_text,
            'attached_path' => $this->attached_path,
            'order_index' => $this->order_index,
            'status' => $this->status ?? 'not_answered',
            'user_answer' => $this->user_answer ?? null,
        ];
    }
}

