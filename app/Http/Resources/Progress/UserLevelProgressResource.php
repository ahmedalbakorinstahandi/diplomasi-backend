<?php

namespace App\Http\Resources\Progress;

use App\Http\Resources\Learning\LevelResource;
use App\Http\Resources\Learning\LessonResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLevelProgressResource extends JsonResource
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
            'level_id' => $this->level_id,
            'current_lesson_id' => $this->current_lesson_id,
            'status' => $this->status,
            'score' => $this->score,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'level' => new LevelResource($this->whenLoaded('level')),
            'current_lesson' => new LessonResource($this->whenLoaded('currentLesson')),
        ];
    }
}

