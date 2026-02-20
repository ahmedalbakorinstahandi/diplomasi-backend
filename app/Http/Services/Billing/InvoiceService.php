<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\Invoice;
use App\Models\Billing\PaymentTransaction;
use App\Models\Users\User;
use App\Services\FilterService;
use App\Services\MessageService;
use Illuminate\Support\Facades\Storage;
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

        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            $needsRegeneration = true;
        } else {
            $currentPath = (string) $invoice->pdf_path;
            $size = (int) Storage::disk('local')->size($currentPath);
            if ($size <= 0) {
                $needsRegeneration = true;
            }
        }

        if ($needsRegeneration) {
            $path = $this->buildAndStorePdf($invoice);
            $invoice->update(['pdf_path' => $path]);
            $invoice = $invoice->fresh();
        }

        $binary = (string) Storage::disk('local')->get((string) $invoice->pdf_path);
        if ($binary === '') {
            $path = $this->buildAndStorePdf($invoice);
            $invoice->update(['pdf_path' => $path]);
            $invoice = $invoice->fresh();
            $binary = (string) Storage::disk('local')->get((string) $invoice->pdf_path);
        }

        return $binary;
    }

    protected function buildAndStorePdf(Invoice $invoice): string
    {
        $user = User::query()->find($invoice->user_id);
        $fullName = trim((string) ($user?->first_name . ' ' . $user?->last_name));
        $fullName = $fullName !== '' ? $fullName : (string) ($user?->email ?? 'Customer');

        $amount = number_format($invoice->amount_minor / 100, 2);
        $html = '<h1>Diplomasi - Invoice</h1>'
            . '<p><strong>Invoice #:</strong> ' . $invoice->invoice_number . '</p>'
            . '<p><strong>Date:</strong> ' . $invoice->issued_at?->toDateString() . '</p>'
            . '<p><strong>Customer:</strong> ' . $fullName . '</p>'
            . '<p><strong>Amount:</strong> ' . $amount . ' ' . $invoice->currency . '</p>'
            . '<p><strong>Status:</strong> ' . $invoice->status . '</p>'
            . '<hr/><p>Thank you for using Diplomasi.</p>';

        $tmpDir = storage_path('app/mpdf/tmp');
        if (!File::exists($tmpDir)) {
            File::makeDirectory($tmpDir, 0775, true);
        }

        if (!is_writable($tmpDir)) {
            MessageService::abort(500, 'PDF temporary directory is not writable');
        }

        $mpdf = new Mpdf([
            'tempDir' => $tmpDir,
        ]);
        $mpdf->WriteHTML($html);
        $binary = $mpdf->Output('', 'S');
        if ($binary === '') {
            MessageService::abort(500, 'Generated invoice PDF is empty');
        }

        $path = 'invoices/' . $invoice->invoice_number . '.pdf';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    protected function generateInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));
    }
}
