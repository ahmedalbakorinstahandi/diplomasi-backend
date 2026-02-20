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

        $customerName = $customerName !== 'Customer' ? $customerName : 'عميلنا';
        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice_issued',
            'to_email' => $user->email,
            'subject' => 'فاتورة ' . $invoice->invoice_number . ' - Diplomasi',
            'content' => $this->renderEmailLayout(
                title: 'فاتورتك جاهزة',
                greeting: 'مرحباً ' . e($customerName) . '،',
                bodyHtml: '<p>تم استلام دفعتك. الفاتورة مرفقة بهذا البريد.</p>'
                    . '<p><strong>رقم الفاتورة:</strong> ' . e($invoice->invoice_number) . '<br/>'
                    . '<strong>المبلغ:</strong> ' . e($amount) . ' ' . e((string) $invoice->currency) . '<br/>'
                    . '<strong>الحالة:</strong> ' . e((string) $invoice->status) . '</p>',
                footer: 'للاستفسار يرجى التواصل مع فريق دبلوماسي.'
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
        $customerName = $customerName !== '' ? $customerName : 'عميلنا';

        $payload = ['payment_transaction_id' => $transaction->id];
        $primaryType = $success ? 'renewal_success' : 'renewal_failed';

        // عند نجاح التجديد: إرفاق الفاتورة كملف PDF في نفس الإيميل
        $attachments = [];
        if ($success) {
            $invoice = Invoice::query()->where('payment_transaction_id', $transaction->id)->first();
            if ($invoice && $invoice->pdf_path) {
                $attachments[] = $invoice->pdf_path;
            }
        }

        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => $primaryType,
            'to_email' => $user->email,
            'subject' => $success
                ? 'تم تجديد اشتراكك بنجاح - Diplomasi'
                : 'فشل تجديد الاشتراك - يرجى التحديث',
            'content' => $success
                ? $this->renderEmailLayout(
                    title: 'تم التجديد بنجاح',
                    greeting: 'مرحباً ' . e($customerName) . '،',
                    bodyHtml: '<p>تم تجديد اشتراكك بنجاح. الفاتورة مرفقة بهذا البريد كملف PDF.</p>'
                        . '<p><strong>المرجع:</strong> ' . e((string) $transaction->merchant_reference_id) . '<br/>'
                        . '<strong>المبلغ:</strong> ' . e($amount) . ' ' . e((string) $transaction->currency) . '</p>',
                    footer: 'شكراً لبقائك مع دبلوماسي.'
                )
                : $this->renderEmailLayout(
                    title: 'لم نتمكن من تجديد اشتراكك',
                    greeting: 'مرحباً ' . e($customerName) . '،',
                    bodyHtml: '<p>لم نتمكن من خصم مبلغ التجديد تلقائياً.</p>'
                        . '<p>يرجى تحديث وسيلة الدفع الافتراضية ثم إعادة المحاولة من التطبيق.</p>'
                        . '<p><strong>المرجع:</strong> ' . e((string) $transaction->merchant_reference_id) . '</p>',
                    footer: 'قد يُحدّ من وصولك إذا لم تُكمل الدفع.'
                ),
            'attachments' => $attachments,
            'payload' => $payload,
            'send_at' => now(),
            'status' => 'pending',
        ]);

        if (!$success) {
            BillingEmailNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'renewal_failed_reminder',
                'to_email' => $user->email,
                'subject' => 'تذكير: دفعة اشتراكك لا تزال معلقة - Diplomasi',
                'content' => $this->renderEmailLayout(
                    title: 'تذكير بالدفع',
                    greeting: 'مرحباً ' . e($customerName) . '،',
                    bodyHtml: '<p>تذكير بأن تجديد اشتراكك لم يُدفع بعد.</p>'
                        . '<p>يرجى تحديث وسيلة الدفع وإكمال الدفع لتجنب الانقطاع.</p>',
                    footer: 'إن كنت قد دفعت مسبقاً، يرجى تجاهل هذا التذكير.'
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
        $attachments = is_array($notification->attachments) ? $notification->attachments : [];
        // إرسال من billing@ دائماً (الفاتورة والتجديد)
        $fromAddress = env('BILLING_MAIL_FROM_ADDRESS') ?: 'billing@diplomasi.app';
        $fromName = env('BILLING_MAIL_FROM_NAME') ?: 'Diplomasi';

        Mail::mailer('billing')->send([], [], function ($message) use ($notification, $attachments, $fromAddress, $fromName) {
            $message->from($fromAddress, $fromName)
                ->replyTo($fromAddress, $fromName)
                ->to($notification->to_email)
                ->subject($notification->subject)
                ->html($notification->content);

            foreach ($attachments as $path) {
                $path = ltrim((string) $path, '/');
                $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($path);
                if (!is_file($fullPath)) {
                    $fullPath = storage_path('app/public/' . $path);
                }
                if (!is_file($fullPath)) {
                    continue;
                }
                $message->attach($fullPath, [
                    'as' => basename($path),
                    'mime' => 'application/pdf',
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