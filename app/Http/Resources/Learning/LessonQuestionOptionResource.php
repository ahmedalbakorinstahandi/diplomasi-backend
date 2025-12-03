<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLessonAnswerMatchResource;
use App\Http\Resources\Progress\UserLessonAnswerOptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonQuestionOptionResource extends JsonResource
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
            'question_id' => $this->question_id,
            'option_text' => $this->option_text,
            'pair_key' => $this->pair_key,
            'is_correct' => $this->is_correct,
            'attached_path' => $this->attached_path,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'lesson_question' => new LessonQuestionResource($this->whenLoaded('lessonQuestion')),
            'user_lesson_answer_options' => UserLessonAnswerOptionResource::collection($this->whenLoaded('userLessonAnswerOptions')),
            'left_user_lesson_answer_matches' => UserLessonAnswerMatchResource::collection($this->whenLoaded('leftUserLessonAnswerMatches')),
            'right_user_lesson_answer_matches' => UserLessonAnswerMatchResource::collection($this->whenLoaded('rightUserLessonAnswerMatches')),
        ];
    }
}

