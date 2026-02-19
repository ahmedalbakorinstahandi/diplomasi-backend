<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Services\Billing\InvoiceService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class BillingHistoryController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }

    public function invoices(Request $request)
    {
        $items = $this->invoiceService->listForUser((int) $request->user()->id, (int) $request->integer('per_page', 20));

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $items,
            'meta' => true,
        ]);
    }

    public function payments(Request $request)
    {
        $items = $this->invoiceService->listPaymentsForUser((int) $request->user()->id, (int) $request->integer('per_page', 20));

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $items,
            'meta' => true,
        ]);
    }

    public function showInvoice(Request $request, int $id)
    {
        $invoice = $this->invoiceService->findUserInvoice((int) $request->user()->id, $id);
        if (!$invoice) {
            return ResponseService::response([
                'success' => false,
                'status' => 404,
                'message' => 'Invoice not found',
            ]);
        }

        $data = $invoice->toArray();
        if ($request->boolean('include_pdf')) {
            $data['pdf_base64'] = base64_encode($this->invoiceService->getPdfBinary($invoice));
        }

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $data,
        ]);
    }

    public function downloadInvoice(Request $request, int $id)
    {
        $invoice = $this->invoiceService->findUserInvoice((int) $request->user()->id, $id);
        if (!$invoice) {
            return ResponseService::response([
                'success' => false,
                'status' => 404,
                'message' => 'Invoice not found',
            ]);
        }

        $binary = $this->invoiceService->getPdfBinary($invoice);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.pdf"',
        ]);
    }
}