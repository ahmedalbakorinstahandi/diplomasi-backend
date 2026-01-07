<?php

namespace App\Http\Resources\Scenarios;

use App\Http\Resources\Learning\LevelResource;
use App\Http\Resources\Learning\LevelTrackResource;
use App\Models\Users\User;
use App\Services\TrackProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioResource extends JsonResource
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
            // Use stored data directly (faster, no recursion)
            $progressPercentage = $this->trackProgressService->getProgressPercentage($this->resource, $userId);
            
            // Check status from stored data
            $attempt = \App\Models\Scenarios\UserScenarioAttempt::where('user_id', $userId)
                ->where('scenario_id', $this->id)
                ->orderBy('started_at', 'desc')
                ->first();
            
            if ($attempt && $attempt->track_status) {
                $status = $attempt->track_status;
            } else {
                // Fallback: check if completed
                $status = $this->trackProgressService->isTrackCompleted($this->resource, $userId) ? 'completed' : 'open';
            }
        }

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'title' => $this->title,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'start_question_id' => $this->start_question_id,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Progress fields
            'status' => $status, // locked, open, completed
            'progress_percentage' => $progressPercentage, // 0-100
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'start_question' => new ScenarioQuestionResource($this->whenLoaded('startQuestion')),
            'scenario_questions' => ScenarioQuestionResource::collection($this->whenLoaded('scenarioQuestions')),
            'user_scenario_attempts' => UserScenarioAttemptResource::collection($this->whenLoaded('userScenarioAttempts')),
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

