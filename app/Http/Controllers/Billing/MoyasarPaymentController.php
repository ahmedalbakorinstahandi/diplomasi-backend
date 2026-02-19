<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Http\Requests\Billing\VerifyMoyasarPaymentRequest;
use App\Http\Services\Billing\MoyasarPaymentService;
use App\Services\ResponseService;

class MoyasarPaymentController extends Controller
{
    public function __construct(
        protected MoyasarPaymentService $moyasarPaymentService
    ) {}

    public function createCheckoutSession(CreateCheckoutSessionRequest $request)
    {
        $result = $this->moyasarPaymentService->createCheckoutSession([
            ...$request->validated(),
            'user_id' => (int) $request->user()->id,
        ]);

        return ResponseService::response([
            'success' => true,
            'status' => 201,
            'data' => $result,
        ]);
    }

    public function verify(VerifyMoyasarPaymentRequest $request)
    {
        $result = $this->moyasarPaymentService->verify([
            ...$request->validated(),
            'user_id' => (int) $request->user()->id,
        ]);

        return ResponseService::response([
            'success' => true,
            'status' => 200,
            'data' => $result,
        ]);
    }
}

