<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentMethodRequest;
use App\Http\Services\Billing\PaymentMethodService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentMethodService $paymentMethodService
    ) {}

    public function index(Request $request)
    {
        $methods = $this->paymentMethodService->listForUser((int) $request->user()->id);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $methods,
        ]);
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $method = $this->paymentMethodService->storeForUser((int) $request->user()->id, $request->validated());

        return ResponseService::response([
            'success' => true,
            'status' => 201,
            'data' => $method,
        ]);
    }
}

