<?php

namespace App\Console\Commands;

use App\Http\Services\Billing\BillingEmailService;
use App\Http\Services\Billing\InvoiceService;
use App\Models\Billing\BillingEmailNotification;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncBillingArtifacts extends Command
{
    protected $signature = 'billing:sync-artifacts {--limit=200}';

    protected $description = 'Create invoices and queue billing emails from finalized transactions';

    public function handle(InvoiceService $invoiceService, BillingEmailService $billingEmailService): int
    {
        $limit = (int) $this->option('limit');

        // معاملات الأحدث أولاً حتى لا تُتخطى معاملات التجديد الأخيرة (الحد 200 كان يهمل الجديدة عند orderBy id تصاعدي)
        $transactions = PaymentTransaction::query()
            ->whereNotNull('finalized_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        Log::channel('single')->info('[billing.sync-artifacts] Start', [
            'limit' => $limit,
            'transactions_count' => $transactions->count(),
        ]);
        $issued = 0;
        $queued = 0;
        $logPath = function_exists('storage_path') ? storage_path('logs/debug-3f6903.log') : base_path('../../debug-3f6903.log');

        foreach ($transactions as $transaction) {
            $invoice = $invoiceService->issueFromTransaction($transaction);
            $invoiceQueued = false;
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
            $renewalBranch = null;
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

            // تصحيح تواريخ الاشتراك و/أو إنشاء حدث renewed من المعاملة المدفوعة للتجديد
            if ($isRenewal && (string) $transaction->status === 'paid') {
                $subscription = Subscription::query()->find($transaction->subscription_id);
                if (!$subscription) {
                    $renewalBranch = 'no_subscription';
                } else {
                    $alreadyEvent = SubscriptionEvent::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('event_type', 'renewed')
                        ->where('meta->payment_transaction_id', $transaction->id)
                        ->exists();

                    if ($transaction->billing_period_start && $transaction->billing_period_end) {
                        $subEnd = $subscription->end_date ? $subscription->end_date->format('Y-m-d H:i:s') : null;
                        $txEnd = \Illuminate\Support\Carbon::parse($transaction->billing_period_end)->format('Y-m-d H:i:s');
                        if ($subEnd === null || $subEnd < $txEnd) {
                            $subscription->update([
                                'status' => 'active',
                                'start_date' => $transaction->billing_period_start,
                                'end_date' => $transaction->billing_period_end,
                            ]);
                            if (!$alreadyEvent) {
                                Log::channel('single')->info('[billing.sync-artifacts] Subscription dates corrected from transaction', [
                                    'subscription_id' => $subscription->id,
                                    'transaction_id' => $transaction->id,
                                ]);
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
                                    'currency' => (string) ($subscription->currency ?? 'USD'),
                                    'meta' => ['payment_transaction_id' => $transaction->id, 'source' => 'sync_artifacts'],
                                ]);
                                $alreadyEvent = true;
                            }
                            $renewalBranch = 'corrected';
                        } elseif (!$alreadyEvent) {
                            // الاشتراك محدّث مسبقاً (مثلاً من attemptRenewal) لكن حدث renewed مفقود — إنشاء الحدث فقط (معالجة بيانات قديمة)
                            Log::channel('single')->info('[billing.sync-artifacts] Heal: create missing renewed event', [
                                'subscription_id' => $subscription->id,
                                'transaction_id' => $transaction->id,
                            ]);
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
                                'currency' => (string) ($subscription->currency ?? 'USD'),
                                'meta' => ['payment_transaction_id' => $transaction->id, 'source' => 'sync_artifacts_heal'],
                            ]);
                            $renewalBranch = 'heal';
                        } else {
                            $renewalBranch = 'already_ok';
                        }
                    } else {
                        Log::channel('single')->debug('[billing.sync-artifacts] Paid renewal transaction without billing_period (e.g. from webhook)', [
                            'transaction_id' => $transaction->id,
                            'subscription_id' => $transaction->subscription_id,
                        ]);
                        $renewalBranch = 'no_billing_period';
                    }
                }
            }

            // #region agent log
            if (is_string($logPath) && $logPath !== '') {
                $payload = ['sessionId' => '3f6903', 'runId' => 'sync_artifacts', 'hypothesisId' => 'C', 'location' => 'SyncBillingArtifacts.php:handle', 'message' => 'Transaction sync', 'data' => ['transaction_id' => $transaction->id, 'has_invoice' => (bool) $invoice, 'invoice_notification_existed' => $invoiceQueued, 'is_renewal' => $isRenewal, 'has_billing_period' => $isRenewal && (bool) $transaction->billing_period_start && (bool) $transaction->billing_period_end, 'renewal_branch' => $renewalBranch ?? 'n/a'], 'timestamp' => (int) round(microtime(true) * 1000)];
                @file_put_contents($logPath, json_encode($payload) . "\n", \FILE_APPEND | \LOCK_EX);
            }
            // #endregion
        }

        Log::channel('single')->info('[billing.sync-artifacts] Done', [
            'invoices_issued' => $issued,
            'notifications_queued' => $queued,
        ]);
        $this->info('Invoices processed: ' . $issued . ', notifications queued: ' . $queued);

        return self::SUCCESS;
    }
}
