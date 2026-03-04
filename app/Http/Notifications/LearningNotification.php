<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;

class LearningNotification
{
    public static function levelCompleted(int $userId, int $levelId, ?string $levelTitle = null): void
    {
        $levelName = $levelTitle && trim($levelTitle) !== '' ? $levelTitle : 'المستوى الحالي';

        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'ممتاز! أنهيت مستوى جديدًا 🌟',
            body: "أحسنت! أكملت {$levelName}. استمر بنفس الحماس للانتقال إلى الخطوة التالية.",
            type: 'level_completed',
            data: [
                'level_id' => $levelId,
                'screen' => 'levels',
            ]
        );
    }

    public static function courseCompleted(int $userId, int $courseId, ?string $courseTitle = null): void
    {
        $courseName = $courseTitle && trim($courseTitle) !== '' ? $courseTitle : 'الكورس';

        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تهانينا! أنهيت كورس كامل 🎉',
            body: "إنجاز رائع! أكملت {$courseName}. افتح شهاداتك واحتفل بتقدمك.",
            type: 'course_completed',
            data: [
                'course_id' => $courseId,
                'screen' => 'courses',
            ]
        );
    }
}
