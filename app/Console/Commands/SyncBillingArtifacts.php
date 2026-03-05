<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\BillingEmailService;
use App\Http\Services\Billing\InvoiceService;
use App\Models\Billing\BillingEmailNotification;
use App\Models\Billing\PaymentTransaction;
use Illuminate\Console\Command;

class SyncBillingArtifacts extends Command
{
    protected $signature = 'billing:sync-artifacts {--limit=200}';

    protected $description = 'Create invoices and queue billing emails from finalized transactions';

    public function handle(InvoiceService $invoiceService, BillingEmailService $billingEmailService): int
    {
        $limit = (int) $this->option('limit');

        $transactions = PaymentTransaction::query()
            ->whereNotNull('finalized_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $issued = 0;
        $queued = 0;

        foreach ($transactions as $transaction) {
            $invoice = $invoiceService->issueFromTransaction($transaction);
            if ($invoice) {
                $issued++;

                $invoiceQueued = BillingEmailNotification::query()
                    ->where('type', 'invoice_issued')
                    ->where('payload->invoice_id', $invoice->id)
                    ->exists();

                if (!$invoiceQueued) {
                    $billingEmailService->queueInvoiceIssued($invoice);
                    $queued++;
                }
            }

            // إشعار/إيميل التجديد للمستخدم فقط عند تجديد اشتراك موجود (subscription_id معرّف)، وليس عند الاشتراك لأول مرة
            $isRenewal = $transaction->subscription_id !== null;
            if ($isRenewal && in_array((string) $transaction->status, ['paid', 'failed'], true)) {
                $type = $transaction->status === 'paid' ? 'renewal_success' : 'renewal_failed';

                $statusQueued = BillingEmailNotification::query()
                    ->where('type', $type)
                    ->where('payload->payment_transaction_id', $transaction->id)
                    ->exists();

                if (!$statusQueued) {
                    $billingEmailService->queueRenewalStatus($transaction, $transaction->status === 'paid');
                    $queued++;
                }
            }
        }

        $this->info('Invoices processed: ' . $issued . ', notifications queued: ' . $queued);

        return self::SUCCESS;
    }
}
