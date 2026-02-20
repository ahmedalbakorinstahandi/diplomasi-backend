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
            ['amount_minor', 'attempt_no', 'plan_id', 'subscription_id'],
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

    public function issueFromTransaction(PaymentTransaction $transaction): Invoice
    {
        $existing = Invoice::query()->where('payment_transaction_id', $transaction->id)->first();
        if ($existing) {
            return $existing;
        }

        $invoice = Invoice::query()->create([
            'user_id' => $transaction->user_id,
            'subscription_id' => $transaction->subscription_id,
            'payment_transaction_id' => $transaction->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => 'issued',
            'amount_minor' => $transaction->amount_minor,
            'currency' => $transaction->currency,
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

        $amount = number_format($invoice->amount_minor / 100, 2);
        $plan = $invoice->paymentTransaction?->plan;
        $planName = $plan ? e((string) $plan->name) : '—';
        $planInterval = $plan && !empty($plan->interval)
            ? (str_starts_with(strtolower((string) $plan->interval), 'year') ? 'سنوي' : 'شهري')
            : '—';
        $reference = $invoice->paymentTransaction?->merchant_reference_id ?? '—';
        $issuedAt = $invoice->issued_at?->format('d/m/Y') ?? $invoice->issued_at?->format('Y-m-d') ?? '—';
        $statusAr = $invoice->status === 'paid' ? 'مدفوعة' : ($invoice->status === 'issued' ? 'صادرة' : e((string) $invoice->status));
        $currencyAr = strtoupper((string) $invoice->currency) === 'SAR' ? 'ريال سعودي' : e((string) $invoice->currency);

        $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><style>body{direction:rtl;text-align:right;font-family:DejaVu Sans,Tahoma,Arial,sans-serif;}</style></head><body style="font-size:12px;line-height:1.6;color:#1a1a2e;">'
            . '<div style="max-width:600px;margin:0 auto;padding:24px;">'
            . '<h1 style="text-align:center;margin:0 0 24px;font-size:20px;color:#1e3a5f;">فاتورة - دبلوماسي</h1>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;direction:rtl;">'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>رقم الفاتورة</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . e($invoice->invoice_number) . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>تاريخ الإصدار</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $issuedAt . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>العميل</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . e($fullName) . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>البريد الإلكتروني</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . e($email) . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>الباقة</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $planName . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>مدة الباقة</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $planInterval . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>مرجع الدفع</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . e((string) $reference) . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>المبلغ</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $amount . ' ' . $currencyAr . '</td></tr>'
            . '<tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;"><strong>الحالة</strong></td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;">' . $statusAr . '</td></tr>'
            . '</table>'
            . '<p style="margin:20px 0 0;padding:12px;background:#f8fafc;border-radius:8px;font-size:11px;color:#64748b;">هذه الفاتورة غير قابلة للاسترداد.</p>'
            . '<p style="margin:24px 0 0;text-align:center;font-size:11px;color:#94a3b8;">شكراً لاستخدامك دبلوماسي.</p>'
            . '</div></body></html>';

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
}
