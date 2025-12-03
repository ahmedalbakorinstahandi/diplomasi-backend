<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLessonAttemptResource;
use App\Http\Resources\Progress\UserLessonQuestionAnswerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonQuestionResource extends JsonResource
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
            'lesson_id' => $this->lesson_id,
            'type' => $this->type,
            'question_text' => $this->question_text,
            'attached_path' => $this->attached_path,
            'explanation' => $this->explanation,
            'score' => $this->score,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'lesson' => new LessonResource($this->whenLoaded('lesson')),
            'lesson_question_options' => LessonQuestionOptionResource::collection($this->whenLoaded('lessonQuestionOptions')),
            'user_lesson_question_answers' => UserLessonQuestionAnswerResource::collection($this->whenLoaded('userLessonQuestionAnswers')),
            'current_user_lesson_attempts' => UserLessonAttemptResource::collection($this->whenLoaded('currentUserLessonAttempts')),
        ];
    }
}

