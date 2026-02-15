<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\SubscriptionService;
use App\Models\Billing\Subscription;
use Illuminate\Console\Command;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process-renewals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Geidea subscription housekeeping jobs';

    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing subscription housekeeping jobs...');

        $periodEndResult = $this->subscriptionService->processPeriodEndCancellations();
        $this->info("Processed period-end cancellations: {$periodEndResult['processed']}, Failed: {$periodEndResult['failed']}");

        $subscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('cancel_at_period_end', false)
            ->where('end_date', '<=', now()->addDays(3)->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->subscriptionService->processAutomaticRenewal($subscription);
                if ($result) {
                    $processed++;
                    $this->info("Processed renewal for subscription ID: {$subscription->id}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("Failed to process renewal for subscription ID: {$subscription->id} - {$e->getMessage()}");
            }
        }

        $this->info("Renewal pre-check completed. Processed: {$processed}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
