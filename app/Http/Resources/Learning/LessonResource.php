<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLessonAttemptResource;
use App\Http\Resources\Progress\UserLessonProgressResource;
use App\Models\Users\User;
use App\Services\TrackProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    protected $trackProgressService;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->trackProgressService = app(TrackProgressService::class);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = User::auth();
        $userId = $user ? $user->id : null;

        $status = 'locked';
        $progressPercentage = 0;

        if ($userId) {
            // Load levelTrack if not already loaded
            if (!$this->relationLoaded('levelTrack')) {
                $this->resource->load('levelTrack');
            }

            if ($this->levelTrack) {
                $status = $this->trackProgressService->getTrackStatus($this->levelTrack, $userId);
            } else {
                // If no levelTrack, check if completed directly
                $status = $this->trackProgressService->isTrackCompleted($this->resource, $userId) ? 'completed' : 'open';
            }
            
            $progressPercentage = $this->trackProgressService->getProgressPercentage($this->resource, $userId);
        }

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
            
            // Progress fields
            'status' => $status, // locked, open, completed
            'progress_percentage' => $progressPercentage, // 0-100
            
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

