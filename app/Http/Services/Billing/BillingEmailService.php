<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\BillingEmailNotification;
use App\Models\Billing\Invoice;
use App\Models\Billing\PaymentTransaction;
use App\Models\Users\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillingEmailService
{
    protected const MAX_ATTEMPTS = 3;

    public function queueInvoiceIssued(Invoice $invoice): void
    {
        $user = User::query()->find($invoice->user_id);
        if (!$user || !$user->email) {
            return;
        }

        $amount = number_format(((int) $invoice->amount_minor) / 100, 2);
        $customerName = trim((string) ($user->first_name . ' ' . $user->last_name));
        $customerName = $customerName !== '' ? $customerName : 'Customer';

        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice_issued',
            'to_email' => $user->email,
            'subject' => 'Invoice ' . $invoice->invoice_number . ' - Diplomasi',
            'content' => $this->renderEmailLayout(
                title: 'Your invoice is ready',
                greeting: 'Hi ' . e($customerName) . ',',
                bodyHtml: '<p>Thank you for your payment. Your invoice is attached to this email.</p>'
                    . '<p><strong>Invoice Number:</strong> ' . e($invoice->invoice_number) . '<br/>'
                    . '<strong>Amount:</strong> ' . e($amount) . ' ' . e((string) $invoice->currency) . '<br/>'
                    . '<strong>Status:</strong> ' . e((string) $invoice->status) . '</p>',
                footer: 'If you need support, please contact Diplomasi support team.'
            ),
            'attachments' => $invoice->pdf_path ? [$invoice->pdf_path] : [],
            'payload' => ['invoice_id' => $invoice->id],
            'send_at' => now(),
            'status' => 'pending',
        ]);
    }

    public function queueRenewalStatus(PaymentTransaction $transaction, bool $success): void
    {
        $user = User::query()->find($transaction->user_id);
        if (!$user || !$user->email) {
            return;
        }

        $amount = number_format(((int) $transaction->amount_minor) / 100, 2);
        $customerName = trim((string) ($user->first_name . ' ' . $user->last_name));
        $customerName = $customerName !== '' ? $customerName : 'Customer';

        $payload = ['payment_transaction_id' => $transaction->id];
        $primaryType = $success ? 'renewal_success' : 'renewal_failed';

        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => $primaryType,
            'to_email' => $user->email,
            'subject' => $success
                ? 'Subscription renewed successfully - Diplomasi'
                : 'Subscription renewal failed - action required',
            'content' => $success
                ? $this->renderEmailLayout(
                    title: 'Renewal completed',
                    greeting: 'Hi ' . e($customerName) . ',',
                    bodyHtml: '<p>Your subscription has been renewed successfully.</p>'
                        . '<p><strong>Reference:</strong> ' . e((string) $transaction->merchant_reference_id) . '<br/>'
                        . '<strong>Amount:</strong> ' . e($amount) . ' ' . e((string) $transaction->currency) . '</p>',
                    footer: 'Thank you for staying with Diplomasi.'
                )
                : $this->renderEmailLayout(
                    title: 'We could not renew your subscription',
                    greeting: 'Hi ' . e($customerName) . ',',
                    bodyHtml: '<p>We were unable to renew your subscription automatically.</p>'
                        . '<p>Please update your default payment method, then retry your payment from the app.</p>'
                        . '<p><strong>Reference:</strong> ' . e((string) $transaction->merchant_reference_id) . '</p>',
                    footer: 'Your access may be limited if payment is not completed.'
                ),
            'payload' => $payload,
            'send_at' => now(),
            'status' => 'pending',
        ]);

        // Scheduled follow-up reminder for failed renewals (large-app standard dunning step).
        if (!$success) {
            BillingEmailNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'renewal_failed_reminder',
                'to_email' => $user->email,
                'subject' => 'Reminder: your subscription payment is still pending',
                'content' => $this->renderEmailLayout(
                    title: 'Payment reminder',
                    greeting: 'Hi ' . e($customerName) . ',',
                    bodyHtml: '<p>This is a friendly reminder that your subscription renewal is still unpaid.</p>'
                        . '<p>Please update your payment method and complete payment to avoid interruption.</p>',
                    footer: 'If you already paid, please ignore this reminder.'
                ),
                'payload' => $payload + ['reminder' => 'd1'],
                'send_at' => now()->addDay(),
                'status' => 'pending',
            ]);
        }
    }

    public function dispatchDue(int $limit = 100): array
    {
        $pending = BillingEmailNotification::query()
            ->where('status', 'pending')
            ->where('send_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($pending as $notification) {
            /** @var BillingEmailNotification $notification */
            try {
                $this->send($notification);
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => (int) $notification->attempts + 1,
                    'last_error' => null,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Billing email sending error: ' . $e->getMessage());
                $attempts = (int) $notification->attempts + 1;
                if ($attempts < self::MAX_ATTEMPTS) {
                    $notification->update([
                        'status' => 'pending',
                        'attempts' => $attempts,
                        'last_error' => $e->getMessage(),
                        'send_at' => now()->addMinutes($attempts * 10),
                    ]);
                } else {
                    $notification->update([
                        'status' => 'failed',
                        'attempts' => $attempts,
                        'last_error' => $e->getMessage(),
                    ]);
                }
                $failed++;
            }
        }

        return ['processed' => $pending->count(), 'sent' => $sent, 'failed' => $failed];
    }

    protected function send(BillingEmailNotification $notification): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => env('BILLING_SMTP_HOST', env('MAIL_HOST')),
            'mail.mailers.smtp.port' => (int) env('BILLING_SMTP_PORT', env('MAIL_PORT', 465)),
            'mail.mailers.smtp.scheme' => env('BILLING_SMTP_ENCRYPTION', env('MAIL_ENCRYPTION', 'ssl')),
            'mail.mailers.smtp.username' => env('BILLING_SMTP_USERNAME', env('MAIL_USERNAME')),
            'mail.mailers.smtp.password' => env('BILLING_SMTP_PASSWORD', env('MAIL_PASSWORD')),
            'mail.from.address' => env('BILLING_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'mail.from.name' => env('BILLING_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Diplomasi')),
        ]);

        $attachments = is_array($notification->attachments) ? $notification->attachments : [];

        Mail::mailer('smtp')->send([], [], function ($message) use ($notification, $attachments) {
            $message->to($notification->to_email)
                ->subject($notification->subject)
                ->html($notification->content);

            foreach ($attachments as $path) {
                $fullPath = storage_path('app/' . ltrim((string) $path, '/'));
                if (!file_exists($fullPath)) {
                    continue;
                }
                $message->attach($fullPath, [
                    'as' => basename($fullPath),
                    'mime' => mime_content_type($fullPath) ?: 'application/pdf',
                ]);
            }
        });
    }

    protected function renderEmailLayout(
        string $title,
        string $greeting,
        string $bodyHtml,
        string $footer
    ): string {
        return '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;max-width:640px;margin:0 auto;padding:20px;">'
            . '<h2 style="margin:0 0 16px;color:#111827;">' . $title . '</h2>'
            . '<p style="margin:0 0 12px;">' . $greeting . '</p>'
            . $bodyHtml
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;" />'
            . '<p style="font-size:12px;color:#6b7280;margin:0;">' . $footer . '</p>'
            . '</div>';
    }
}