<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;

class AccountNotification
{
    public static function verified(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تم تفعيل حسابك بنجاح ✅',
            body: 'أهلًا بك في دبلوماسي! حسابك أصبح جاهزًا ويمكنك البدء بالتعلم الآن.',
            type: 'account_verified',
            data: [
                'screen' => 'home',
            ]
        );
    }

    public static function passwordChanged(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تم تغيير كلمة المرور 🔐',
            body: 'تم تحديث كلمة المرور لحسابك بنجاح. إذا لم تكن أنت من قام بهذا الإجراء، تواصل مع الدعم فورًا.',
            type: 'password_changed',
            data: [
                'screen' => 'profile',
            ]
        );
    }

    public static function newDeviceLogin(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تسجيل دخول من جهاز جديد 📱',
            body: 'تم تسجيل الدخول إلى حسابك من جهاز جديد. إذا لم تكن أنت، غيّر كلمة المرور مباشرة.',
            type: 'login_new_device',
            data: [
                'screen' => 'security',
                'user_id' => $userId,
            ]
        );
    }

    public static function banned(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تم تقييد حسابك',
            body: 'تم تقييد حسابك مؤقتًا. للاستفسار أو المراجعة، يرجى التواصل مع فريق الدعم.',
            type: 'account_banned',
            data: [
                'screen' => 'support',
            ]
        );
    }
}
