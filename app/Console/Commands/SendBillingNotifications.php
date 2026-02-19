<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\BillingEmailService;
use Illuminate\Console\Command;

class SendBillingNotifications extends Command
{
    protected $signature = 'billing:send-notifications {--limit=100}';

    protected $description = 'Send due billing emails (invoices and renewal notifications)';

    public function handle(BillingEmailService $billingEmailService): int
    {
        $result = $billingEmailService->dispatchDue((int) $this->option('limit'));

        $this->info('Processed: ' . $result['processed'] . ', sent: ' . $result['sent'] . ', failed: ' . $result['failed']);

        return self::SUCCESS;
    }
}