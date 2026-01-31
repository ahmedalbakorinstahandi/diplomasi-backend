<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\SubscriptionService;
use App\Models\Billing\Subscription;
use App\Services\StripeService;
use Illuminate\Console\Command;

class SyncStripeSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:sync-stripe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync subscription statuses with Stripe';

    protected StripeService $stripeService;
    protected SubscriptionService $subscriptionService;

    public function __construct(StripeService $stripeService, SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->stripeService = $stripeService;
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Syncing subscriptions with Stripe...');

        $subscriptions = Subscription::whereNotNull('stripe_subscription_id')
            ->where('status', '!=', 'cancelled')
            ->get();

        $synced = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $stripeSubscription = $this->stripeService->getSubscription($subscription->stripe_subscription_id);

                $status = match ($stripeSubscription->status) {
                    'active' => 'active',
                    'canceled' => 'cancelled',
                    'past_due' => 'past_due',
                    'unpaid' => 'expired',
                    default => $subscription->status,
                };

                $subscription->update([
                    'status' => $status,
                    'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
                    'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start)->format('Y-m-d'),
                    'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
                    'end_date' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('Y-m-d'),
                ]);

                $synced++;
                $this->info("Synced subscription ID: {$subscription->id}");
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to sync subscription ID: {$subscription->id} - {$e->getMessage()}");
            }
        }

        $this->info("Sync completed. Synced: {$synced}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
