<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\InvoicePermission;
use App\Http\Resources\Billing\InvoiceResource;
use App\Http\Services\Billing\AdminInvoiceService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AdminInvoiceController extends Controller
{
    public function __construct(
        protected AdminInvoiceService $invoiceService
    ) {}

    public function index(Request $request)
    {
        InvoicePermission::canView();

        $invoices = $this->invoiceService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $invoices,
            'meta' => true,
            'resource' => InvoiceResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        $invoice = $this->invoiceService->show($id);
        $data = (new InvoiceResource($invoice))->toArray(request());

        return ResponseService::response([
            'success' => true,
            'data' => $data,
            'status' => 200,
        ]);
    }

    public function download(int $id)
    {
        InvoicePermission::canDownload();

        $invoice = $this->invoiceService->show($id);
        $binary = $this->invoiceService->getPdfBinary($invoice);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.pdf"',
        ]);
    }
}
