<?php

namespace App\Listeners;

use App\Events\UserCourseCompleted;
use App\Http\Notifications\LearningNotification;

class SendCourseCompletedNotification
{
    public function handle(UserCourseCompleted $event): void
    {
        $progress = $event->userCourse->loadMissing(['course']);

        LearningNotification::courseCompleted(
            userId: (int) $progress->user_id,
            courseId: (int) $progress->course_id,
            courseTitle: (string) ($progress->course?->title ?? '')
        );
    }
}
