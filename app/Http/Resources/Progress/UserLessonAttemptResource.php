<?php

namespace App\Http\Resources\Progress;

use App\Http\Resources\Learning\LessonQuestionResource;
use App\Http\Resources\Learning\LessonResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLessonAttemptResource extends JsonResource
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
            'user_id' => $this->user_id,
            'lesson_id' => $this->lesson_id,
            'status' => $this->status,
            'score' => $this->score,
            'current_question_id' => $this->current_question_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'total_time' => $this->total_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'lesson' => new LessonResource($this->whenLoaded('lesson')),
            'current_question' => new LessonQuestionResource($this->whenLoaded('currentQuestion')),
            'user_lesson_question_answers' => UserLessonQuestionAnswerResource::collection($this->whenLoaded('userLessonQuestionAnswers')),
        ];
    }
}

