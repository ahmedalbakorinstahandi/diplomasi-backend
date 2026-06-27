<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\PaymentPermission;
use App\Http\Resources\Billing\PaymentTransactionResource;
use App\Http\Services\Billing\AdminPaymentService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function __construct(
        protected AdminPaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        PaymentPermission::canView();

        $payments = $this->paymentService->index($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $payments,
            'meta' => true,
            'resource' => PaymentTransactionResource::class,
            'status' => 200,
        ]);
    }

    public function show(int $id)
    {
        $payment = $this->paymentService->show($id);

        return ResponseService::response([
            'success' => true,
            'data' => $payment,
            'resource' => PaymentTransactionResource::class,
            'status' => 200,
        ]);
    }
}
