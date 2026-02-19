<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\Invoice;
use App\Models\Billing\PaymentTransaction;
use App\Models\Users\User;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class InvoiceService
{
    public function listForUser(int $userId, int $perPage = 20)
    {
        return Invoice::query()
            ->where('user_id', $userId)
            ->with(['paymentTransaction'])
            ->orderByDesc('issued_at')
            ->paginate($perPage);
    }

    public function listPaymentsForUser(int $userId, int $perPage = 20)
    {
        return PaymentTransaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('finalized_at')
            ->with(['invoice'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findUserInvoice(int $userId, int $invoiceId): ?Invoice
    {
        return Invoice::query()
            ->where('user_id', $userId)
            ->where('id', $invoiceId)
            ->first();
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
        if (!$invoice->pdf_path || !Storage::disk('local')->exists($invoice->pdf_path)) {
            $path = $this->buildAndStorePdf($invoice);
            $invoice->update(['pdf_path' => $path]);
            $invoice = $invoice->fresh();
        }

        return (string) Storage::disk('local')->get((string) $invoice->pdf_path);
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

        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);
        $binary = $mpdf->Output('', 'S');

        $path = 'invoices/' . $invoice->invoice_number . '.pdf';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    protected function generateInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid('', true), -6));
    }
}