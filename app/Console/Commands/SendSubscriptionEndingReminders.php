<?php

namespace App\Console\Commands;

use App\Http\Notifications\BillingNotification;
use App\Models\Billing\Subscription;
use App\Models\System\Notification;
use Illuminate\Console\Command;

class SendSubscriptionEndingReminders extends Command
{
    protected $signature = 'billing:send-ending-reminders {--days=3} {--limit=500}';

    protected $description = 'Send token-based reminder notifications before subscription ending date';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $targetDate = now()->addDays($days)->toDateString();

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->whereDate('end_date', $targetDate)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            $alreadySentToday = Notification::query()
                ->where('type', 'subscription_ending_reminder')
                ->where('user_id', $subscription->user_id)
                ->whereDate('created_at', now()->toDateString())
                ->where('data->subscription_id', $subscription->id)
                ->exists();

            if ($alreadySentToday) {
                continue;
            }

            BillingNotification::endingReminder(
                userId: (int) $subscription->user_id,
                subscriptionId: (int) $subscription->id,
                endDate: (string) $subscription->end_date?->format('Y-m-d')
            );
            $sent++;
        }

        $this->info('Subscription ending reminders sent: ' . $sent);

        return self::SUCCESS;
    }
}
