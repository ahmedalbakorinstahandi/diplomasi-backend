<?php

namespace App\Http\Services\Billing;

use App\Http\Notifications\BillingNotification;
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

        $alreadyQueued = BillingEmailNotification::query()
            ->where('type', 'invoice_issued')
            ->where('payload->invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'sent'])
            ->exists();
        if ($alreadyQueued) {
            Log::channel('single')->info('[billing.email] Invoice already queued, skip', ['invoice_id' => $invoice->id]);
            return;
        }

        $invoice->loadMissing(['paymentTransaction']);
        if (!$invoice->paymentTransaction || (string) $invoice->paymentTransaction->status !== 'paid') {
            Log::channel('single')->info('[billing.email] Skip invoice email: transaction not paid', [
                'invoice_id' => $invoice->id,
                'transaction_status' => $invoice->paymentTransaction?->status,
            ]);
            return;
        }

        $isRenewal = (int) ($invoice->paymentTransaction->subscription_id ?? 0) > 0;

        try {
            $content = $this->buildInvoiceEmailHtml($invoice, $user, $isRenewal);
        } catch (\Throwable $e) {
            Log::warning('Invoice email HTML build failed, using fallback: ' . $e->getMessage());
            $customerName = trim((string) ($user->first_name . ' ' . $user->last_name)) ?: 'عميلنا';
            $invoice->loadMissing(['paymentTransaction']);
            $usdAmount = number_format(((int) $invoice->amount_minor) / 100, 2);
            $sarAmount = number_format(((int) ($invoice->paymentTransaction?->amount_minor ?? 0)) / 100, 2);
            $bodyText = $isRenewal
                ? 'تم تجديد اشتراكك بنجاح. الفاتورة مرفقة بهذا البريد كملف PDF.'
                : 'تم استلام دفعتك. الفاتورة مرفقة بهذا البريد كملف PDF.';
            $content = $this->renderEmailLayout(
                title: $isRenewal ? 'فاتورة تجديد الاشتراك' : 'فاتورتك جاهزة',
                greeting: 'مرحباً ' . e($customerName) . '،',
                bodyHtml: '<p>' . $bodyText . '</p>'
                    . '<p><strong>رقم الفاتورة:</strong> ' . e($invoice->invoice_number) . '<br/>'
                    . '<strong>السعر المرجعي:</strong> ' . $usdAmount . ' USD<br/>'
                    . '<strong>المبلغ المدفوع:</strong> ' . $sarAmount . ' SAR</p>',
                footer: 'للاستفسار يرجى التواصل مع فريق دبلوماسي.'
            );
        }

        $subject = $isRenewal
            ? 'فاتورة تجديد الاشتراك ' . $invoice->invoice_number . ' - دبلوماسي'
            : 'فاتورة ' . $invoice->invoice_number . ' - دبلوماسي';

        BillingEmailNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice_issued',
            'to_email' => $user->email,
            'subject' => $subject,
            'content' => $content,
            'attachments' => $invoice->pdf_path ? [$invoice->pdf_path] : [],
            'payload' => ['invoice_id' => $invoice->id],
            'send_at' => now(),
            'status' => 'pending',
        ]);

        BillingNotification::invoiceIssued(
            userId: (int) $user->id,
            invoiceId: (int) $invoice->id,
            invoiceNumber: (string) $invoice->invoice_number
        );
        Log::channel('single')->info('[billing.email] Invoice issued queued', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'user_id' => $user->id,
            'is_renewal' => $isRenewal,
        ]);
    }

    /**
     * تصميم إيميل الفاتورة مطابق لتصميم الـ PDF (لوغو، تفاصيل، ضريبة 15%).
     * عند التجديد: عنوان ونص يوضح أن الفاتورة لتجديد الاشتراك.
     */
    protected function buildInvoiceEmailHtml(Invoice $invoice, User $user, bool $isRenewal = false): string
    {
        $invoice->loadMissing(['paymentTransaction.plan']);
        $fullName = trim((string) ($user->first_name . ' ' . $user->last_name));
        $fullName = $fullName !== '' ? $fullName : 'عميلنا';
        $email = $user->email ?? '—';

        $usdTotalMajor = (float) ($invoice->amount_minor / 100);
        $usdAmount = number_format($usdTotalMajor, 2);
        $chargedSarMinor = (int) ($invoice->paymentTransaction?->amount_minor ?? 0);
        $chargedSarMajor = (float) ($chargedSarMinor / 100);
        $sarAmount = number_format($chargedSarMajor, 2);
        $chargedSarCurrency = (string) strtoupper((string) ($invoice->paymentTransaction?->currency ?? 'SAR'));

        $plan = $invoice->paymentTransaction?->plan;
        $planName = $plan ? e((string) $plan->name) : '—';
        $planInterval = $plan && !empty($plan->interval)
            ? (str_starts_with(strtolower((string) $plan->interval), 'year') ? 'سنوي' : 'شهري')
            : '—';
        $reference = $invoice->paymentTransaction?->merchant_reference_id ?? '—';
        $issuedAt = $invoice->issued_at?->format('d/m/Y') ?? '—';
        $logoUrl = config('app.invoice_logo_url') ?: rtrim((string) config('app.url'), '/') . '/images/logo.png';
        $vatReg = config('app.invoice_vat_registration_number');
        $paymentMethodDisplay = $this->formatPaymentMethodForEmail($invoice->paymentTransaction);
        $bankDisclaimer = 'يتم تنفيذ الدفع بالريال السعودي (SAR). قد يختلف المبلغ النهائي الظاهر في كشف البنك/البطاقة قليلًا حسب سعر الصرف المعتمد من البنك وأي رسوم تحويل أو معاملات عملة أجنبية.';

        $logoHtml = $logoUrl
            ? '<img src="' . e($logoUrl) . '" style="height:48px;display:block;" alt="دبلوماسي" />'
            : '<span style="font-size:22px;font-weight:700;color:#1e3a5f;">دبلوماسي</span>';

        $titleText = $isRenewal ? 'فاتورة تجديد الاشتراك' : 'الفاتورة';
        $introText = $isRenewal
            ? 'تم تجديد اشتراكك بنجاح. الفاتورة مرفقة بهذا البريد كملف PDF.'
            : 'تم استلام دفعتك. الفاتورة مرفقة بهذا البريد كملف PDF.';

        $html = '<div dir="rtl" style="font-family:Arial,Tahoma,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#1a1a2e;font-size:14px;line-height:1.5;">'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">'
            . '<tr><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;">' . $logoHtml . '</td><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;text-align:left;"><h1 style="margin:0;font-size:20px;color:#1e3a5f;">' . e($titleText) . '</h1></td></tr>'
            . '</table>'
            . '<p style="margin:0 0 16px;">مرحباً ' . e($fullName) . '، ' . e($introText) . '</p>'
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
            . '<tr><td style="padding:10px 14px;"><strong>السعر المرجعي</strong></td><td style="padding:10px 14px;">' . $usdAmount . ' USD</td></tr>'
            . '<tr><td style="padding:10px 14px;"><strong>المبلغ المدفوع عبر البوابة</strong></td><td style="padding:10px 14px;">' . $sarAmount . ' ' . $chargedSarCurrency . '</td></tr>'
            . '</table>'
            . '<p style="margin:0 0 8px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1d4ed8;">' . e($bankDisclaimer) . '</p>'
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
        if (!$transaction) {
            return '—';
        }
        if ($transaction->provider === 'apple') {
            return 'Apple In-App Purchase';
        }
        if (!is_array($transaction->raw_response)) {
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
        Log::channel('single')->info('[billing.email] Queue renewal status', [
            'transaction_id' => $transaction->id,
            'subscription_id' => $transaction->subscription_id,
            'success' => $success,
        ]);
        $user = User::query()->find($transaction->user_id);
        if (!$user || !$user->email) {
            return;
        }

        $amount = number_format(((int) $transaction->amount_minor) / 100, 2);
        $customerName = trim((string) ($user->first_name . ' ' . $user->last_name));
        $customerName = $customerName !== '' ? $customerName : 'عميلنا';

        $payload = ['payment_transaction_id' => $transaction->id];

        // عند نجاح التجديد: نُسجّل سجلاً فقط لتفادي تكرار الإشعار، ولا نرسل إيميلاً (المستخدم يستلم إيميل الفاتورة المفصّل من queueInvoiceIssued)
        if ($success) {
            BillingEmailNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'renewal_success',
                'to_email' => $user->email,
                'subject' => 'تم تجديد اشتراكك بنجاح - Diplomasi',
                'content' => '',
                'attachments' => [],
                'payload' => $payload,
                'send_at' => now(),
                'status' => 'pending',
            ]);
        } else {
            BillingEmailNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'renewal_failed',
                'to_email' => $user->email,
                'subject' => 'فشل تجديد الاشتراك - يرجى التحديث',
                'content' => $this->renderEmailLayout(
                    title: 'لم نتمكن من تجديد اشتراكك',
                    greeting: 'مرحباً ' . e($customerName) . '،',
                    bodyHtml: '<p>لم نتمكن من خصم مبلغ التجديد تلقائياً.</p>'
                        . '<p>يرجى تحديث وسيلة الدفع الافتراضية ثم إعادة المحاولة من التطبيق.</p>'
                        . '<p><strong>المرجع:</strong> ' . e((string) $transaction->merchant_reference_id) . '</p>',
                    footer: 'قد يُحدّ من وصولك إذا لم تُكمل الدفع.'
                ),
                'attachments' => [],
                'payload' => $payload,
                'send_at' => now(),
                'status' => 'pending',
            ]);
        }

        // إشعار التجديد في التطبيق فقط عند تجديد اشتراك موجود، وليس عند الاشتراك لأول مرة
        $isRenewal = $transaction->subscription_id !== null;
        if ($isRenewal) {
            if ($success) {
                BillingNotification::renewalSuccess((int) $user->id);
            } else {
                BillingNotification::renewalFailed((int) $user->id);
            }
        }

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
                // renewal_success: لا نرسل إيميلاً (المستخدم استلم إيميل الفاتورة المفصّل فقط)
                if ($notification->type !== 'renewal_success') {
                    $this->send($notification);
                }
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