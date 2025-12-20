<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLessonAttemptResource;
use App\Http\Resources\Progress\UserLessonProgressResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'level_id' => $this->level_id,
            'lesson_number' => $this->lesson_number,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'content' => $this->content,
            'order_index' => $this->order_index,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'lesson_questions' => LessonQuestionResource::collection($this->whenLoaded('lessonQuestions')),
            'lesson_summary' => new LessonSummaryResource($this->whenLoaded('lessonSummary')),
            'user_lesson_progress' => UserLessonProgressResource::collection($this->whenLoaded('userLessonProgress')),
            'user_lesson_attempts' => UserLessonAttemptResource::collection($this->whenLoaded('userLessonAttempts')),
            'level_track' => new LevelTrackResource($this->whenLoaded('levelTrack')),
        ];
    }
}

