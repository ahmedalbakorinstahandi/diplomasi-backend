<?php

namespace App\Listeners;

use App\Events\UserCourseCompleted;
use App\Events\UserLevelCompleted;
use App\Http\Services\System\CertificateService;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use Illuminate\Support\Facades\Log;

class CheckCertificateEligibility
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(UserLevelCompleted|UserCourseCompleted $event): void
    {
        try {
            if ($event instanceof UserLevelCompleted) {
                $this->handleLevelCompleted($event->userLevelProgress);
            }
            // Course-wide certificates are disabled; UserCourseCompleted is ignored for issuance.
        } catch (\Exception $e) {
            Log::error('Certificate issuance error: ' . $e->getMessage(), [
                'event' => get_class($event),
                'exception' => $e,
            ]);
        }
    }

    private function handleLevelCompleted(UserLevelProgress $userLevelProgress): void
    {
        if (!$userLevelProgress->relationLoaded('level')) {
            $userLevelProgress->load('level');
        }

        $level = $userLevelProgress->level;
        if (!$level) {
            Log::warning('Level not found for UserLevelProgress', [
                'user_level_progress_id' => $userLevelProgress->id,
                'level_id' => $userLevelProgress->level_id,
            ]);

            return;
        }

        if (!$level->has_certificate) {
            Log::info('Level does not have certificate', [
                'level_id' => $level->id,
                'title' => $level->title,
            ]);

            return;
        }

        $userId = $userLevelProgress->user_id;
        $levelId = $userLevelProgress->level_id;
        $courseId = $level->course_id;

        try {
            $this->certificateService->checkCertificateEligibility($userId, $courseId, $levelId);
            $this->certificateService->issueCertificate($userId, $courseId, $levelId);
            Log::info('Certificate issued for level', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'level_id' => $levelId,
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            Log::info('User not eligible for level certificate', [
                'user_id' => $userId,
                'course_id' => $courseId,
                'level_id' => $levelId,
            ]);
        }
    }
}
