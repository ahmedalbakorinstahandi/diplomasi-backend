<?php

namespace App\Http\Services\Billing;

use App\Http\Permissions\Billing\InvoicePermission;
use App\Models\Billing\Invoice;
use App\Services\FilterService;
use App\Services\MessageService;

class AdminInvoiceService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function index(array $filters = [])
    {
        $filters = $this->normalizeFilters($filters);

        $query = Invoice::query()
            ->with(['paymentTransaction', 'user', 'subscription']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'issued_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
            unset($filters['search']);
        }

        $query = InvoicePermission::filterIndex($query);

        return FilterService::applyFilters(
            $query,
            $filters,
            ['invoice_number', 'currency', 'status'],
            ['amount_minor', 'subscription_id', 'payment_transaction_id', 'user_id'],
            ['issued_at', 'due_at', 'paid_at', 'created_at'],
            ['invoice_number', 'status', 'currency', 'user_id', 'subscription_id'],
            ['status', 'currency'],
        );
    }

    public function show(int $id): Invoice
    {
        InvoicePermission::canShow();

        $invoice = Invoice::with(['paymentTransaction', 'user', 'subscription'])->find($id);

        if (!$invoice) {
            MessageService::abort(404, 'messages.invoice.not_found');
        }

        return $invoice;
    }

    public function getPdfBinary(Invoice $invoice): string
    {
        return $this->invoiceService->getPdfBinary($invoice);
    }

    protected function normalizeFilters(array $filters): array
    {
        if (!empty($filters['sort_by']) && empty($filters['sort_field'])) {
            $filters['sort_field'] = $filters['sort_by'];
        }

        if (!empty($filters['from_date']) && empty($filters['created_at_from'])) {
            $filters['created_at_from'] = $filters['from_date'];
        }

        if (!empty($filters['to_date']) && empty($filters['created_at_to'])) {
            $filters['created_at_to'] = $filters['to_date'];
        }

        return $filters;
    }
}
