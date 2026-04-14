<?php

namespace App\Http\Resources\Learning;

use App\Http\Resources\Progress\UserLevelProgressResource;
use App\Http\Resources\Scenarios\ScenarioResource;
use App\Http\Resources\System\CertificateResource;
use App\Models\Users\User;
use App\Services\CertificateEligibilityService;
use App\Services\MediaUrlService;
use App\Services\RequestContext;
use App\Services\TrackProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
{
    protected static $levelProgressDataCache = null;

    /**
     * Set progress data cache (loaded in batch)
     *
     * @param array $progressData
     * @return void
     */
    public static function setProgressDataCache(array $progressData): void
    {
        self::$levelProgressDataCache = $progressData;
    }

    /**
     * Clear progress data cache
     *
     * @return void
     */
    public static function clearProgressDataCache(): void
    {
        self::$levelProgressDataCache = null;
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

        $isCompleted = false;
        $accessStatus = 'locked';
        $certificate = null;
        $certificateEligibility = null;

        if ($userId) {
            // Use cached progress data if available (loaded in batch)
            if (self::$levelProgressDataCache !== null && isset(self::$levelProgressDataCache[$this->id])) {
                $progressData = self::$levelProgressDataCache[$this->id];
                $isCompleted = $progressData['is_completed'] ?? false;
                $accessStatus = $progressData['access_status'] ?? 'locked';
                $certificate = $progressData['certificate'] ?? null;
            } else {
                // Fallback: load individually (slower but works)
                $trackProgressService = app(TrackProgressService::class);
                $isCompleted = $trackProgressService->isLevelCompleted($this->resource, $userId);
                $accessStatus = $trackProgressService->getLevelAccessStatus($this->resource, $userId);
                $certificate = $trackProgressService->getUserCertificateForLevel($this->resource, $userId);
            }

            $eligibility = app(CertificateEligibilityService::class)
                ->evaluateLevelForUser($userId, $this->resource);
            $certificateEligibility = [
                'is_eligible' => (bool) $eligibility->is_eligible,
                'final_state' => $eligibility->final_state,
                'artifact_status' => $eligibility->artifact_status,
                'regeneration_reason' => $eligibility->regeneration_reason,
                'requires_subscription_for_certificate' => !$this->is_free,
            ];
        }

        $base = [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'level_number' => $this->level_number,
            'title' => $this->title,
            'description' => $this->description,
            'is_published' => $this->is_published,
            'is_free' => $this->is_free,
            'has_certificate' => $this->has_certificate,
            'order_index' => $this->order_index,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Progress data
            'is_completed' => $isCompleted,
            'access_status' => $accessStatus,
            'certificate' => $certificate ? new CertificateResource($certificate) : null,
            'certificate_eligibility' => $certificateEligibility,
            
            // Relationships
            'course' => new CourseResource($this->whenLoaded('course')),
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
            'scenarios' => ScenarioResource::collection($this->whenLoaded('scenarios')),
            'level_tracks' => LevelTrackResource::collection($this->whenLoaded('levelTracks')),
            'user_level_progress' => UserLevelProgressResource::collection($this->whenLoaded('userLevelProgress')),
            'certificates' => CertificateResource::collection($this->whenLoaded('certificates')),
        ];

        if (RequestContext::isDashboard()) {
            $base['certificate_template_path'] = $this->certificate_template_path;
            $base['certificate_template_url'] = MediaUrlService::toUrl($this->certificate_template_path);
            $base['certificate_template_config'] = $this->certificate_template_config;
        }

        return $base;
    }
}

