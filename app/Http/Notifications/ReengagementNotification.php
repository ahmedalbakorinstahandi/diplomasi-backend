<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;

class ReengagementNotification
{
    public static function reminder(
        int $userId,
        string $title,
        string $body,
        int $amount,
        string $unit,
        string $ruleSignature,
        string $basisTimestamp,
        ?string $deepLink = null
    ): void {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: $title,
            body: $body,
            type: 'reengagement_reminder',
            data: array_filter([
                'screen' => 'home',
                'amount' => $amount,
                'unit' => $unit,
                'rule_signature' => $ruleSignature,
                'basis_timestamp' => $basisTimestamp,
                'deep_link' => $deepLink,
            ], static fn($value) => $value !== null),
            showAfterRead: false
        );
    }
}
