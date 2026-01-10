<?php

namespace App\Listeners;

use App\Events\UserCourseCompleted;
use App\Events\UserLevelCompleted;
use App\Http\Services\System\CertificateService;
use App\Models\Learning\Course;
use App\Models\Learning\Level;
use App\Models\Progress\UserCourse;
use App\Models\Progress\UserLevelProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CheckCertificateEligibility
{
    protected CertificateService $certificateService;

    /**
     * Create the event listener.
     */
    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    /**
     * Handle the event.
     */
    public function handle(UserLevelCompleted|UserCourseCompleted $event): void
    {
        try {
            if ($event instanceof UserLevelCompleted) {
                $this->handleLevelCompleted($event->userLevelProgress);
            } elseif ($event instanceof UserCourseCompleted) {
                $this->handleCourseCompleted($event->userCourse);
            }
        } catch (\Exception $e) {
            Log::error('Certificate issuance error: ' . $e->getMessage(), [
                'event' => get_class($event),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle UserLevelCompleted event
     */
    private function handleLevelCompleted(UserLevelProgress $userLevelProgress): void
    {
        // تحميل العلاقة بشكل صريح إذا لم تكن محملة
        if (!$userLevelProgress->relationLoaded('level')) {
            $userLevelProgress->load('level');
        }
        
        $level = $userLevelProgress->level;
        if (!$level) {
            Log::warning("Level not found for UserLevelProgress", [
                'user_level_progress_id' => $userLevelProgress->id,
                'level_id' => $userLevelProgress->level_id,
            ]);
            return;
        }
        
        if (!$level->has_certificate) {
            Log::info("Level does not have certificate", [
                'level_id' => $level->id,
                'title' => $level->title,
                'has_certificate' => $level->has_certificate,
            ]);
            return; // هذا المستوى لا يحتوي على شهادة
        }

        $userId = $userLevelProgress->user_id;
        $levelId = $userLevelProgress->level_id;
        $courseId = $level->course_id;

        // التحقق من الأهلية لإصدار شهادة للمستوى
        $eligibility = $this->certificateService->checkCertificateEligibility($userId, $courseId, $levelId);
        if ($eligibility['eligible']) {
            // إصدار شهادة للمستوى
            $this->certificateService->issueCertificate($userId, $courseId, $levelId);
            Log::info("Certificate issued for level", [
                'user_id' => $userId,
                'course_id' => $courseId,
                'level_id' => $levelId,
            ]);
        }

        // التحقق من إكمال جميع المستويات في الكورس لإصدار شهادة الكورس
        $this->checkAndIssueCourseCertificate($userId, $courseId);
    }

    /**
     * Handle UserCourseCompleted event
     */
    private function handleCourseCompleted(UserCourse $userCourse): void
    {
        $userId = $userCourse->user_id;
        $courseId = $userCourse->course_id;

        // التحقق من إكمال جميع المستويات وإصدار شهادة الكورس
        $this->checkAndIssueCourseCertificate($userId, $courseId);
    }

    /**
     * التحقق من إكمال جميع المستويات وإصدار شهادة الكورس
     */
    private function checkAndIssueCourseCertificate(int $userId, int $courseId): void
    {
        $course = Course::find($courseId);
        if (!$course) {
            return;
        }

        // التحقق من أن جميع المستويات مكتملة
        $levels = $course->levels()->get();
        $allLevelsCompleted = true;

        foreach ($levels as $level) {
            $userLevelProgress = UserLevelProgress::where('user_id', $userId)
                ->where('level_id', $level->id)
                ->first();

            if (!$userLevelProgress || $userLevelProgress->status !== 'completed') {
                $allLevelsCompleted = false;
                break;
            }
        }

        if (!$allLevelsCompleted) {
            return; // بعض المستويات غير مكتملة
        }

        // التحقق من أن آخر مستوى يحتوي على has_certificate = true
        $lastLevel = $levels->sortByDesc('level_number')->first();
        if ($lastLevel && !$lastLevel->has_certificate) {
            return; // آخر مستوى لا يحتوي على شهادة
        }

        // التحقق من الأهلية لإصدار شهادة للكورس
        $eligibility = $this->certificateService->checkCertificateEligibility($userId, $courseId, null);
        if ($eligibility['eligible']) {
            // إصدار شهادة للكورس
            $this->certificateService->issueCertificate($userId, $courseId, null);
            Log::info("Certificate issued for course", [
                'user_id' => $userId,
                'course_id' => $courseId,
            ]);
        }
    }
}
