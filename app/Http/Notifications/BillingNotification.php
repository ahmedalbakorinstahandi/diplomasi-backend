<?php

namespace App\Http\Notifications;

use App\Http\Services\System\NotificationService;
use App\Models\Billing\Subscription;

class BillingNotification
{
    public static function subscriptionActivated(Subscription $subscription): void
    {
        $subscription->loadMissing('plan');
        $planName = (string) ($subscription->plan?->name ?? 'الباقة');

        app(NotificationService::class)->sendToUser(
            userId: (int) $subscription->user_id,
            title: 'اشتراكك أصبح فعالًا ✨',
            body: "تم تفعيل اشتراكك في باقة {$planName} بنجاح. استمتع بكامل مزايا المنصة.",
            type: 'subscription_activated',
            data: [
                'subscription_id' => (int) $subscription->id,
                'screen' => 'plans',
            ]
        );
    }

    public static function invoiceIssued(int $userId, int $invoiceId, string $invoiceNumber): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'فاتورتك جاهزة 📄',
            body: "تم إصدار الفاتورة رقم {$invoiceNumber}. يمكنك مراجعتها وتحميلها من سجل الفواتير.",
            type: 'invoice_issued',
            data: [
                'invoice_id' => $invoiceId,
                'screen' => 'billing_history',
            ]
        );
    }

    public static function renewalSuccess(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تم تجديد اشتراكك بنجاح ✅',
            body: 'رائع! تم تجديد اشتراكك تلقائيًا واستمرار وصولك إلى المحتوى بدون انقطاع.',
            type: 'renewal_success',
            data: [
                'screen' => 'plans',
            ]
        );
    }

    public static function renewalFailed(int $userId): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'تعذّر تجديد الاشتراك ⚠️',
            body: 'لم نتمكن من تجديد اشتراكك. يرجى تحديث وسيلة الدفع والمحاولة مرة أخرى لتجنب توقف الخدمة.',
            type: 'renewal_failed',
            data: [
                'screen' => 'plans',
            ]
        );
    }

    public static function endingReminder(int $userId, int $subscriptionId, string $endDate): void
    {
        app(NotificationService::class)->sendToUser(
            userId: $userId,
            title: 'اشتراكك يقترب من الانتهاء ⏳',
            body: "اشتراكك سينتهي بتاريخ {$endDate}. جدد الآن حتى لا تفوّت تقدمك التعليمي.",
            type: 'subscription_ending_reminder',
            data: [
                'subscription_id' => $subscriptionId,
                'end_date' => $endDate,
                'screen' => 'plans',
            ]
        );
    }
}
