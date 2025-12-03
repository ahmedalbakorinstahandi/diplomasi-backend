<?php

namespace App\Http\Resources\Progress;

use App\Http\Resources\Learning\LessonQuestionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLessonQuestionAnswerResource extends JsonResource
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
            'attempt_id' => $this->attempt_id,
            'question_id' => $this->question_id,
            'step_index' => $this->step_index,
            'is_correct' => $this->is_correct,
            'score' => $this->score,
            'time_spent' => $this->time_spent,
            'answered_at' => $this->answered_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user_lesson_attempt' => new UserLessonAttemptResource($this->whenLoaded('userLessonAttempt')),
            'lesson_question' => new LessonQuestionResource($this->whenLoaded('lessonQuestion')),
            'user_lesson_answer_options' => UserLessonAnswerOptionResource::collection($this->whenLoaded('userLessonAnswerOptions')),
            'user_lesson_answer_matches' => UserLessonAnswerMatchResource::collection($this->whenLoaded('userLessonAnswerMatches')),
        ];
    }
}

