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
        $hasPreviousAttempts = false;

        if ($userId) {
            // Use stored data directly (faster, no recursion)
            $progressPercentage = $this->trackProgressService->getProgressPercentage($this->resource, $userId);

            // Check status from stored data
            $progress = \App\Models\Progress\UserLessonProgress::where('user_id', $userId)
                ->where('lesson_id', $this->id)
                ->first();

            if ($progress && $progress->track_status) {
                $status = $progress->track_status;
            } else {
                // Fallback: check if completed
                $status = $this->trackProgressService->isTrackCompleted($this->resource, $userId) ? 'completed' : 'open';
            }

            $hasPreviousAttempts = \App\Models\Progress\UserLessonAttempt::where('user_id', $userId)
                ->where('lesson_id', $this->id)
                ->exists();
        }

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'lesson_number' => $this->lesson_number,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'content' => $this->content ?? "",
            'order_index' => $this->order_index,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Progress fields
            'status' => $status, // locked, open, completed
            'progress_percentage' => $progressPercentage, // 0-100
            'has_previous_attempts' => $hasPreviousAttempts,

            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'lesson_questions' => LessonQuestionResource::collection($this->whenLoaded('lessonQuestions')),
            'lesson_summary' => new LessonSummaryResource($this->whenLoaded('lessonSummary')),
            'user_lesson_progress' => UserLessonProgressResource::collection($this->whenLoaded('userLessonProgress')),
            'user_lesson_attempts' => UserLessonAttemptResource::collection($this->whenLoaded('userLessonAttempts')),
            // level_track is excluded when used inside LevelTrackResource to prevent recursion
            'level_track' => $this->when(
                $this->relationLoaded('levelTrack') && !\App\Http\Resources\Learning\LevelTrackResource::isInsideLevelTrackResource(),
                function () {
                    return new \App\Http\Resources\Learning\LevelTrackResource($this->levelTrack);
                }
            ),
        ];
    }
}
