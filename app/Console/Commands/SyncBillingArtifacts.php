<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\BillingEmailService;
use App\Http\Services\Billing\InvoiceService;
use App\Models\Billing\BillingEmailNotification;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
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

            // تصحيح تواريخ الاشتراك إذا الويب هوك لم يحدّثها: تحديث من المعاملة المدفوعة للتجديد
            if ($isRenewal && (string) $transaction->status === 'paid' && $transaction->billing_period_start && $transaction->billing_period_end) {
                $subscription = Subscription::query()->find($transaction->subscription_id);
                if ($subscription) {
                    $subEnd = $subscription->end_date ? $subscription->end_date->format('Y-m-d H:i:s') : null;
                    $txEnd = $transaction->billing_period_end ? \Illuminate\Support\Carbon::parse($transaction->billing_period_end)->format('Y-m-d H:i:s') : null;
                    if ($txEnd && ($subEnd === null || $subEnd < $txEnd)) {
                        $subscription->update([
                            'status' => 'active',
                            'start_date' => $transaction->billing_period_start,
                            'end_date' => $transaction->billing_period_end,
                        ]);
                        $alreadyEvent = SubscriptionEvent::query()
                            ->where('subscription_id', $subscription->id)
                            ->where('event_type', 'renewed')
                            ->where('meta->payment_transaction_id', $transaction->id)
                            ->exists();
                        if (!$alreadyEvent) {
                            $subscription->loadMissing('plan');
                            $amountCharged = $transaction->amount_minor ? (float) ($transaction->amount_minor / 100) : (float) ($subscription->plan?->price ?? $subscription->price);
                            SubscriptionEvent::query()->create([
                                'subscription_id' => $subscription->id,
                                'event_type' => 'renewed',
                                'plan_id' => (int) $subscription->plan_id,
                                'status' => 'active',
                                'start_date' => $transaction->billing_period_start,
                                'end_date' => $transaction->billing_period_end,
                                'plan_price' => $subscription->plan?->price ?? $subscription->price,
                                'amount_charged' => $amountCharged,
                                'amount_refunded' => 0,
                                'currency' => (string) ($subscription->currency ?? 'SAR'),
                                'meta' => ['payment_transaction_id' => $transaction->id, 'source' => 'sync_artifacts'],
                            ]);
                        }
                    }
                }
            }
        }

        $this->info('Invoices processed: ' . $issued . ', notifications queued: ' . $queued);

        return self::SUCCESS;
    }
}
