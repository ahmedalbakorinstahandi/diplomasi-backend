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

        try {
            $content = $this->buildInvoiceEmailHtml($invoice, $user);
        } catch (\Throwable $e) {
            Log::warning('Invoice email HTML build failed, using fallback: ' . $e->getMessage());
            $customerName = trim((string) ($user->first_name . ' ' . $user->last_name)) ?: 'عميلنا';
            $amount = number_format(((int) $invoice->amount_minor) / 100, 2);
            $content = $this->renderEmailLayout(
                title: 'فاتورتك جاهزة',
                greeting: 'مرحباً ' . e($customerName) . '،',
                bodyHtml: '<p>تم استلام دفعتك. الفاتورة مرفقة بهذا البريد كملف PDF.</p>'
                    . '<p><strong>رقم الفاتورة:</strong> ' . e($invoice->invoice_number) . '<br/>'
                    . '<strong>المبلغ:</strong> ' . $amount . ' ر.س (شامل ضريبة القيمة المضافة 15%)</p>',
                footer: 'للاستفسار يرجى التواصل مع فريق دبلوماسي.'
            );
        }

        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice_issued',
            'to_email' => $user->email,
            'subject' => 'فاتورة ' . $invoice->invoice_number . ' - دبلوماسي',
            'content' => $content,
            'attachments' => $invoice->pdf_path ? [$invoice->pdf_path] : [],
            'payload' => ['invoice_id' => $invoice->id],
            'send_at' => now(),
            'status' => 'pending',
        ]);
    }

    /**
     * تصميم إيميل الفاتورة مطابق لتصميم الـ PDF (لوغو، تفاصيل، ضريبة 15%).
     */
    protected function buildInvoiceEmailHtml(Invoice $invoice, User $user): string
    {
        $invoice->loadMissing(['paymentTransaction.plan']);
        $fullName = trim((string) ($user->first_name . ' ' . $user->last_name));
        $fullName = $fullName !== '' ? $fullName : 'عميلنا';
        $email = $user->email ?? '—';

        $totalSar = (float) ($invoice->amount_minor / 100);
        $amount = number_format($totalSar, 2);

        $plan = $invoice->paymentTransaction?->plan;
        $planName = $plan ? e((string) $plan->name) : '—';
        $planInterval = $plan && !empty($plan->interval)
            ? (str_starts_with(strtolower((string) $plan->interval), 'year') ? 'سنوي' : 'شهري')
            : '—';
        $reference = $invoice->paymentTransaction?->merchant_reference_id ?? '—';
        $issuedAt = $invoice->issued_at?->format('d/m/Y') ?? '—';
        $currencyAr = 'ر.س';
        $logoUrl = config('app.invoice_logo_url') ?: rtrim((string) config('app.url'), '/') . '/images/logo.png';
        $vatReg = config('app.invoice_vat_registration_number');
        $paymentMethodDisplay = $this->formatPaymentMethodForEmail($invoice->paymentTransaction);

        $logoHtml = $logoUrl
            ? '<img src="' . e($logoUrl) . '" style="height:48px;display:block;" alt="دبلوماسي" />'
            : '<span style="font-size:22px;font-weight:700;color:#1e3a5f;">دبلوماسي</span>';

        $html = '<div dir="rtl" style="font-family:Arial,Tahoma,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#1a1a2e;font-size:14px;line-height:1.5;">'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">'
            . '<tr><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;">' . $logoHtml . '</td><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;text-align:left;"><h1 style="margin:0;font-size:20px;color:#1e3a5f;">الفاتورة</h1></td></tr>'
            . '</table>'
            . '<p style="margin:0 0 16px;">مرحباً ' . e($fullName) . '، تم استلام دفعتك. الفاتورة مرفقة بهذا البريد كملف PDF.</p>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:13px;">'
            . '<tr><td style="padding:6px 12px 6px 0;"><strong>التاريخ</strong></td><td style="padding:6px 12px;"><strong>تعريف الطلب</strong></td><td style="padding:6px 0 6px 12px;"><strong>رقم المستند</strong></td></tr>'
            . '<tr><td style="padding:6px 12px 6px 0;">' . $issuedAt . '</td><td style="padding:6px 12px;">' . e((string) $reference) . '</td><td style="padding:6px 0 6px 12px;">' . e($invoice->invoice_number) . '</td></tr>'
            . '<tr><td style="padding:8px 12px 8px 0;"><strong>اسم العميل</strong></td><td style="padding:8px 12px 8px 0;"><strong>البريد الإلكتروني</strong></td><td style="padding:8px 0 8px 12px;"><strong>طريقة الدفع</strong></td></tr>'
            . '<tr><td style="padding:6px 12px 6px 0;">' . e($fullName) . '</td><td style="padding:6px 12px 6px 0;">' . e($email) . '</td><td style="padding:6px 0 6px 12px;">' . $paymentMethodDisplay . '</td></tr>'
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;">'
            . '<tr><td colspan="2" style="padding:10px 14px;background:#1e3a5f;color:#fff;font-weight:700;border-radius:8px 8px 0 0;">دبلوماسي - تفاصيل الاشتراك</td></tr>'
            . '<tr><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><strong>الباقة</strong></td><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">' . $planName . '</td></tr>'
            . '<tr><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><strong>مدة الباقة</strong></td><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">' . $planInterval . '</td></tr>'
            . '<tr><td style="padding:10px 14px;"><strong>المبلغ (شامل ضريبة القيمة المضافة 15%)</strong></td><td style="padding:10px 14px;">' . $amount . ' ' . $currencyAr . '</td></tr>'
            . '</table>'
            . '<p style="margin:0 0 8px;padding:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;font-size:12px;color:#92400e;">هذه الفاتورة غير قابلة للاسترداد.</p>';
        if ($vatReg) {
            $html .= '<p style="margin:0 0 16px;font-size:11px;color:#64748b;">رقم تسجيل ضريبة القيمة المضافة في المملكة العربية السعودية: ' . e($vatReg) . '</p>';
        }
        $html .= '<p style="margin:0;text-align:center;font-size:12px;color:#94a3b8;">شكراً لاستخدامك دبلوماسي.</p></div>';
        return $html;
    }

    /**
     * تنسيق وسيلة الدفع: نوع البطاقة + .... + آخر 4 أرقام (الباقي مشفّر).
     */
    protected function formatPaymentMethodForEmail(?PaymentTransaction $transaction): string
    {
        if (!$transaction || !is_array($transaction->raw_response)) {
            return '—';
        }
        $source = $transaction->raw_response['source'] ?? [];
        $company = trim((string) ($source['company'] ?? ''));
        $number = trim((string) ($source['number'] ?? ''));
        $digits = preg_replace('/\D/', '', $number);
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : null;
        if ($company === '' && $last4 === null) {
            return '—';
        }
        $brand = $company !== '' ? e($company) : 'بطاقة';
        return $last4 !== null ? $brand . ' .... ' . $last4 : $brand;
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