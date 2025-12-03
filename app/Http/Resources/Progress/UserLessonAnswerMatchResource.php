<?php

namespace App\Http\Resources\Progress;

use App\Http\Resources\Learning\LessonQuestionOptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLessonAnswerMatchResource extends JsonResource
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
            'user_answer_id' => $this->user_answer_id,
            'left_option_id' => $this->left_option_id,
            'right_option_id' => $this->right_option_id,
            'is_correct' => $this->is_correct,
            'created_at' => $this->created_at,
            
            // Relationships
            'user_lesson_question_answer' => new UserLessonQuestionAnswerResource($this->whenLoaded('userLessonQuestionAnswer')),
            'left_option' => new LessonQuestionOptionResource($this->whenLoaded('leftOption')),
            'right_option' => new LessonQuestionOptionResource($this->whenLoaded('rightOption')),
        ];
    }
}

