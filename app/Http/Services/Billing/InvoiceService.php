<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\Invoice;
use App\Models\Billing\PaymentTransaction;
use App\Models\Users\User;
use App\Services\FileService;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

class InvoiceService
{
    public function listForUser(int $userId, array $filters = [])
    {
        $query = Invoice::query()
            ->where('user_id', $userId)
            ->with(['paymentTransaction'])
            ->orderByDesc('id');

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'issued_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $invoices = FilterService::applyFilters(
            $query,
            $filters,
            ['invoice_number', 'currency', 'status'],
            ['amount_minor', 'subscription_id', 'payment_transaction_id'],
            ['issued_at', 'due_at', 'paid_at', 'created_at'],
            ['invoice_number', 'status', 'currency'],
            ['status', 'currency']
        );

        return $invoices;
    }

    public function listPaymentsForUser(int $userId, array $filters = [])
    {
        $query = PaymentTransaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('finalized_at')
            ->with(['invoice'])
            ->orderByDesc('id');

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'id';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $payments = FilterService::applyFilters(
            $query,
            $filters,
            ['merchant_reference_id', 'given_id', 'provider_payment_id', 'gateway_status', 'status', 'currency', 'provider'],
            ['amount_minor', 'display_amount_minor', 'attempt_no', 'plan_id', 'subscription_id'],
            ['billing_period_start', 'billing_period_end', 'finalized_at', 'verified_at', 'next_retry_at', 'created_at'],
            ['provider', 'status', 'gateway_status', 'currency', 'plan_id', 'subscription_id'],
            ['status', 'gateway_status', 'currency', 'provider'],
        );

        return $payments;
    }

    public function findUserInvoice(int $userId, int $invoiceId): ?Invoice
    {
        $invoice = Invoice::query()
            ->where('user_id', $userId)
            ->where('id', $invoiceId)
            ->with(['paymentTransaction'])
            ->first();

        if (!$invoice) {
            MessageService::abort(404, message: 'الفاتورة غير موجودة');
        }

        return $invoice;
    }

    public function issueFromTransaction(PaymentTransaction $transaction): ?Invoice
    {
        $existing = Invoice::query()->where('payment_transaction_id', $transaction->id)->first();
        if ($existing) {
            return $existing;
        }

        if ((string) $transaction->status !== 'paid') {
            return null;
        }

        $displayCurrency = (string) ($transaction->display_currency ?? 'USD');
        $displayAmountMinor = $transaction->display_amount_minor ?? $transaction->amount_minor;

        $invoice = Invoice::query()->create([
            'user_id' => $transaction->user_id,
            'subscription_id' => $transaction->subscription_id,
            'payment_transaction_id' => $transaction->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => 'issued',
            // USD reference snapshot (what user sees in-app).
            'amount_minor' => (int) $displayAmountMinor,
            'currency' => $displayCurrency,
            'issued_at' => now(),
            'paid_at' => $transaction->status === 'paid' ? now() : null,
            'meta' => [
                'transaction_status' => $transaction->status,
                'gateway_status' => $transaction->gateway_status,
            ],
        ]);

        $pdfPath = $this->buildAndStorePdf($invoice);
        $invoice->update(['pdf_path' => $pdfPath]);

        return $invoice->fresh();
    }

    public function getPdfBinary(Invoice $invoice): string
    {
        $needsRegeneration = false;

        if (!$invoice->pdf_path || FileService::fileSize((string) $invoice->pdf_path) <= 0) {
            $needsRegeneration = true;
        }

        if ($needsRegeneration) {
            $path = $this->buildAndStorePdf($invoice);
            $invoice->update(['pdf_path' => $path]);
            $invoice = $invoice->fresh();
        }

        $binary = (string) (FileService::readContent((string) $invoice->pdf_path) ?? '');
        if ($binary === '') {
            $path = $this->buildAndStorePdf($invoice);
            $invoice->update(['pdf_path' => $path]);
            $invoice = $invoice->fresh();
            $binary = (string) (FileService::readContent((string) $invoice->pdf_path) ?? '');
        }

        if ($binary === '') {
            MessageService::abort(500, 'Invoice PDF is empty after regeneration');
        }

        return $binary;
    }

    protected function buildAndStorePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['paymentTransaction.plan']);
        $user = User::query()->find($invoice->user_id);
        $fullName = trim((string) ($user?->first_name . ' ' . $user?->last_name));
        $fullName = $fullName !== '' ? $fullName : (string) ($user?->email ?? 'العميل');
        $email = $user?->email ?? '—';

        $usdTotalMajor = (float) ($invoice->amount_minor / 100);
        $usdAmount = number_format($usdTotalMajor, 2);

        $plan = $invoice->paymentTransaction?->plan;
        $planName = $plan ? e((string) $plan->name) : '—';
        $planInterval = $plan && !empty($plan->interval)
            ? (str_starts_with(strtolower((string) $plan->interval), 'year') ? 'سنوي' : 'شهري')
            : '—';
        $reference = $invoice->paymentTransaction?->merchant_reference_id ?? '—';
        $issuedAt = $invoice->issued_at?->format('d/m/Y') ?? $invoice->issued_at?->format('Y-m-d') ?? '—';
        $statusAr = $invoice->status === 'paid' ? 'مدفوعة' : ($invoice->status === 'issued' ? 'صادرة' : e((string) $invoice->status));
        $currencyAr = match (strtoupper((string) $invoice->currency)) {
            'USD' => 'USD',
            default => e((string) $invoice->currency),
        };

        $chargedSarMinor = (int) ($invoice->paymentTransaction?->amount_minor ?? 0);
        $chargedSarMajor = (float) ($chargedSarMinor / 100);
        $chargedSarAmount = number_format($chargedSarMajor, 2);
        $chargedSarCurrency = (string) strtoupper((string) ($invoice->paymentTransaction?->currency ?? 'SAR'));

        $bankDisclaimer = 'تمت معالجة الدفع بالريال السعودي (SAR). قد يختلف المبلغ النهائي الظاهر في كشف البنك/البطاقة قليلاً حسب سعر الصرف المعتمد من البنك وأي رسوم تحويل أو معاملات عملة أجنبية.';
        $logoUrl = config('app.invoice_logo_url') ?: rtrim((string) config('app.url'), '/') . '/images/logo.png';
        $vatReg = config('app.invoice_vat_registration_number');
        $paymentMethodDisplay = $this->formatPaymentMethodForInvoice($invoice->paymentTransaction);

        $logoHtml = $logoUrl
            ? '<img src="' . e($logoUrl) . '" style="height:52px;display:block;" alt="دبلوماسي" />'
            : '<div style="font-size:24px;font-weight:700;color:#1e3a5f;">دبلوماسي</div>';

        $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:DejaVu Sans,Tahoma,Arial,sans-serif;}</style></head><body style="font-size:11px;line-height:1.5;color:#1a1a2e;margin:0;padding:20px;">'
            . '<table style="width:100%;max-width:600px;margin:0 auto;border-collapse:collapse;">'
            . '<tr><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;">' . $logoHtml . '</td><td style="padding-bottom:16px;border-bottom:2px solid #1e3a5f;text-align:left;"><h1 style="margin:0;font-size:22px;color:#1e3a5f;">الفاتورة</h1></td></tr>'
            . '</table>'
            . '<table style="width:100%;max-width:600px;margin:20px auto 0;border-collapse:collapse;">'
            . '<tr><td style="padding:6px 12px 6px 0;vertical-align:top;width:33%;"><strong>التاريخ</strong><br/>' . $issuedAt . '</td><td style="padding:6px 12px;vertical-align:top;width:33%;"><strong>تعريف الطلب</strong><br/>' . e((string) $reference) . '</td><td style="padding:6px 0 6px 12px;vertical-align:top;width:34%;"><strong>رقم المستند</strong><br/>' . e($invoice->invoice_number) . '</td></tr>'
            . '<tr><td style="padding:6px 12px 6px 0;vertical-align:top;"><strong>اسم العميل</strong><br/>' . e($fullName) . '</td><td style="padding:6px 12px;vertical-align:top;"><strong>البريد الإلكتروني</strong><br/>' . e($email) . '</td><td style="padding:6px 0 6px 12px;vertical-align:top;"><strong>طريقة الدفع</strong><br/>' . $paymentMethodDisplay . '</td></tr>'
            . '</table>'
            . '<table style="width:100%;max-width:600px;margin:24px auto 0;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">'
            . '<tr><td colspan="2" style="padding:10px 14px;background:#1e3a5f;color:#fff;font-weight:700;border-radius:8px 8px 0 0;">دبلوماسي - تفاصيل الاشتراك</td></tr>'
            . '<tr><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><strong>الباقة</strong></td><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">' . $planName . '</td></tr>'
            . '<tr><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><strong>مدة الباقة</strong></td><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">' . $planInterval . '</td></tr>'
            . '<tr><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><strong>نوع العملية</strong></td><td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">اشتراك</td></tr>'
            . '<tr><td style="padding:10px 14px;"><strong>السعر المرجعي</strong></td><td style="padding:10px 14px;">' . $usdAmount . ' ' . $currencyAr . '</td></tr>'
            . '<tr><td style="padding:10px 14px;"><strong>المبلغ المدفوع عبر البوابة</strong></td><td style="padding:10px 14px;">' . $chargedSarAmount . ' ' . $chargedSarCurrency . '</td></tr>'
            . '</table>'
            . '<p style="margin:12px auto 0;max-width:600px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:10px;color:#1d4ed8;">' . e($bankDisclaimer) . '</p>'
            . '<p style="margin:20px auto 0;max-width:600px;padding:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;font-size:10px;color:#92400e;">هذه الفاتورة غير قابلة للاسترداد.</p>';
        if ($vatReg) {
            $html .= '<p style="margin:12px auto 0;max-width:600px;font-size:10px;color:#64748b;">رقم تسجيل ضريبة القيمة المضافة في المملكة العربية السعودية: ' . e($vatReg) . '</p>';
        }
        $html .= '<p style="margin:20px auto 0;max-width:600px;text-align:center;font-size:10px;color:#94a3b8;">شكراً لاستخدامك دبلوماسي.</p>'
            . '</body></html>';

        $tmpDir = storage_path('app/mpdf/tmp');
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0775, true);
        }

        if (!is_writable($tmpDir)) {
            MessageService::abort(500, 'PDF temporary directory is not writable');
        }

        $mpdf = new Mpdf([
            'tempDir' => $tmpDir,
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoArabic' => true,
        ]);
        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);
        $binary = $mpdf->Output('', 'S');
        if ($binary === '') {
            MessageService::abort(500, 'Generated invoice PDF is empty');
        }

        $path = 'invoices/' . $invoice->invoice_number . '.pdf';
        FileService::storeContent($binary, $path);

        return $path;
    }

    protected function generateInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));
    }

    /**
     * تنسيق وسيلة الدفع للفاتورة: نوع البطاقة + .... + آخر 4 أرقام (مثل فاتورة طليق).
     */
    protected function formatPaymentMethodForInvoice(?PaymentTransaction $transaction): string
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
}
