<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Scenarios\ScenarioResource;
use App\Models\Users\User;
use App\Services\TrackProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelTrackResource extends JsonResource
{
    protected static $progressDataCache = null;
    protected static $insideLevelTrackResource = false;

    /**
     * Set progress data cache (loaded in batch)
     *
     * @param array $progressData
     * @return void
     */
    public static function setProgressDataCache(array $progressData): void
    {
        self::$progressDataCache = $progressData;
    }

    /**
     * Clear progress data cache
     *
     * @return void
     */
    public static function clearProgressDataCache(): void
    {
        self::$progressDataCache = null;
    }

    /**
     * Check if we're inside LevelTrackResource (to prevent recursion)
     *
     * @return bool
     */
    public static function isInsideLevelTrackResource(): bool
    {
        return self::$insideLevelTrackResource;
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
        $accessReason = null;
        $nextAccessibleTrackId = null;

        if ($userId) {
            // Use cached progress data if available (loaded in batch)
            if (self::$progressDataCache !== null && isset(self::$progressDataCache[$this->id])) {
                $progressData = self::$progressDataCache[$this->id];
                $status = $progressData['status'];
                $progressPercentage = $progressData['progress_percentage'];
                $isAccessible = $progressData['is_accessible'];
                $accessReason = $progressData['access_reason'] ?? null;
                $nextAccessibleTrackId = $progressData['next_accessible_track_id'] ?? null;
            } else {
                // Fallback: load individually (slower but works)
                $trackProgressService = app(TrackProgressService::class);
                $status = $trackProgressService->getTrackStatus($this->resource, $userId);
                $progressPercentage = $trackProgressService->getProgressPercentage($this->trackable, $userId);
                $isAccessible = $trackProgressService->canAccessTrack($this->resource, $userId);
                $accessReason = $isAccessible ? null : $trackProgressService->getTrackBlockingReason($this->resource, $userId);
                $nextTrack = $trackProgressService->getNextAccessibleTrackForLevel($this->level_id, $userId, (int) $this->order_index);
                $nextAccessibleTrackId = $nextTrack?->id;
            }
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
            'access_reason' => $accessReason,
            'next_accessible_track_id' => $nextAccessibleTrackId,
            
            // Relationships
            'level' => new LevelResource($this->whenLoaded('level')),
            'trackable' => $this->when($this->relationLoaded('trackable'), function () {
                // Prevent recursion by setting flag
                self::$insideLevelTrackResource = true;
                
                $trackable = $this->trackable;
                if (!$trackable) {
                    self::$insideLevelTrackResource = false;
                    return null;
                }
                
                // Don't load levelTrack to prevent recursion
                $trackable->unsetRelation('levelTrack');
                
                $result = null;
                if ($this->trackable_type === 'App\\Models\\Learning\\Lesson') {
                    $result = new LessonResource($trackable);
                } elseif ($this->trackable_type === 'App\\Models\\Scenarios\\Scenario') {
                    $result = new ScenarioResource($trackable);
                }
                
                self::$insideLevelTrackResource = false;
                return $result;
            }),
        ];
    }
}

