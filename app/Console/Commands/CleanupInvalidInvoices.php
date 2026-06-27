<?php

namespace App\Console\Commands;

use App\Models\Billing\BillingEmailNotification;
use App\Models\Billing\Invoice;
use App\Services\FileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupInvalidInvoices extends Command
{
    protected $signature = 'billing:cleanup-invalid-invoices {--dry-run : Preview changes without applying them}';

    protected $description = 'Remove invoices and pending emails issued for non-paid payment transactions';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $invalidInvoices = Invoice::query()
            ->whereHas('paymentTransaction', function ($query) {
                $query->where('status', '!=', 'paid');
            })
            ->with('paymentTransaction')
            ->orderByDesc('id')
            ->get();

        $this->info('Invalid invoices found: ' . $invalidInvoices->count());

        $deletedInvoices = 0;
        $cancelledEmails = 0;
        $deletedPdfs = 0;

        foreach ($invalidInvoices as $invoice) {
            $txStatus = (string) ($invoice->paymentTransaction?->status ?? 'unknown');
            $this->line("Invoice {$invoice->invoice_number} (id={$invoice->id}) tx_status={$txStatus}");

            $pendingEmails = BillingEmailNotification::query()
                ->where('type', 'invoice_issued')
                ->where('payload->invoice_id', $invoice->id)
                ->where('status', 'pending')
                ->get();

            if (!$dryRun) {
                DB::transaction(function () use ($invoice, $pendingEmails, &$cancelledEmails, &$deletedInvoices, &$deletedPdfs) {
                    foreach ($pendingEmails as $email) {
                        $email->delete();
                        $cancelledEmails++;
                    }

                    if ($invoice->pdf_path) {
                        FileService::deleteFile((string) $invoice->pdf_path);
                        $deletedPdfs++;
                    }

                    $invoice->delete();
                    $deletedInvoices++;
                });
            } else {
                $cancelledEmails += $pendingEmails->count();
                if ($invoice->pdf_path) {
                    $deletedPdfs++;
                }
                $deletedInvoices++;
            }
        }

        $summary = [
            'dry_run' => $dryRun,
            'invalid_invoices' => $invalidInvoices->count(),
            'deleted_invoices' => $deletedInvoices,
            'cancelled_pending_emails' => $cancelledEmails,
            'deleted_pdfs' => $deletedPdfs,
        ];

        Log::channel('single')->info('[billing.cleanup-invalid-invoices] Done', $summary);
        $this->table(array_keys($summary), [array_values($summary)]);

        if ($dryRun) {
            $this->warn('Dry run only. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
