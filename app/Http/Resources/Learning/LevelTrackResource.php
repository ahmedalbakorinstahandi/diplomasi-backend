<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Scenarios\ScenarioResource;
use App\Models\Users\User;
use App\Services\TrackProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelTrackResource extends JsonResource
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
        $isAccessible = false;

        if ($userId) {
            $status = $this->trackProgressService->getTrackStatus($this->resource, $userId);
            $progressPercentage = $this->trackProgressService->getProgressPercentage($this->trackable, $userId);
            $isAccessible = $this->trackProgressService->canAccessTrack($this->resource, $userId);
        }

        return [
            'id' => $this->id,
            'level_id' => $this->level_id,
            'trackable_id' => $this->trackable_id,
            'trackable_type' => $this->trackable_type,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Progress fields
            'status' => $status, // locked, open, completed
            'progress_percentage' => $progressPercentage, // 0-100
            'is_accessible' => $isAccessible,
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'trackable' => $this->when($this->relationLoaded('trackable'), function () use ($userId) {
                if ($this->trackable_type === 'App\\Models\\Learning\\Lesson') {
                    return new LessonResource($this->trackable);
                } elseif ($this->trackable_type === 'App\\Models\\Scenarios\\Scenario') {
                    return new ScenarioResource($this->trackable);
                }
                return null;
            }),
        ];
    }
}

