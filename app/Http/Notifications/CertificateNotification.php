<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;
use App\Models\System\Certificate;

class CertificateNotification
{
    public static function issued(Certificate $certificate): void
    {
        $certificate->loadMissing(['course']);

        app(NotificationService::class)->sendToUser(
            userId: (int) $certificate->user_id,
            title: 'شهادة جديدة بانتظارك 🎓',
            body: 'رائع! تم إصدار شهادتك في كورس ' . (string) ($certificate->course?->title ?? 'الكورس') . '. افتحها الآن وشارك إنجازك.',
            type: 'certificate_issued',
            data: [
                'certificate_id' => (int) $certificate->id,
                'course_id' => (int) $certificate->course_id,
                'level_id' => $certificate->level_id ? (int) $certificate->level_id : null,
                'screen' => 'certificate_detail',
            ]
        );
    }
}
