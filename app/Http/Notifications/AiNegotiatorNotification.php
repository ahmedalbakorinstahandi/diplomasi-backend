<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;

class AiNegotiatorNotification
{
    public static function settingsChanged(array $userIds, string $customMessage): int
    {
        $notifications = app(NotificationService::class)->sendToUsers(
            userIds: $userIds,
            title: 'تحديث في إعدادات المفاوض الذكي',
            body: $customMessage,
            type: 'ai_negotiator_settings_changed',
            data: [
                'screen' => 'ai_negotiator',
            ]
        );

        return $notifications->count();
    }
}
