<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\MoyasarPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewals extends Command
{
    /**
     * @var string
     */
    protected $signature = 'billing:process-renewals {--limit=100}';

    /**
     * @var string
     */
    protected $description = 'Process due subscription renewal attempts using saved token payment methods';

    public function __construct(
        protected MoyasarPaymentService $moyasarPaymentService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 100;

        $result = $this->moyasarPaymentService->processDueRenewalsBatch($limit);

        Log::channel('single')->info('[billing.renewal] Batch completed', [
            'processed_subscriptions' => $result['processed_subscriptions'],
            'created_attempts' => $result['created_attempts'],
            'limit' => $limit,
        ]);
        $this->info('Renewal batch completed.');
        $this->line('Processed subscriptions: ' . $result['processed_subscriptions']);
        $this->line('Created attempts: ' . $result['created_attempts']);

        return self::SUCCESS;
    }
}

