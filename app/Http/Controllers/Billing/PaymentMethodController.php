<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Services\Billing\PaymentMethodService;
use App\Models\Users\User;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    protected $paymentMethodService;

    public function __construct(PaymentMethodService $paymentMethodService)
    {
        $this->paymentMethodService = $paymentMethodService;
    }

    /**
     * List user's payment methods
     */
    public function index(Request $request)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $paymentMethods = $this->paymentMethodService->index($user);

        return ResponseService::response([
            'success' => true,
            'data' => $paymentMethods,
            'status' => 200,
        ]);
    }

    /**
     * Store a new payment method
     */
    public function store(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $paymentMethod = $this->paymentMethodService->create($user, $request->payment_method_id);

        return ResponseService::response([
            'success' => true,
            'data' => $paymentMethod,
            'message' => 'Payment method added successfully',
            'status' => 201,
        ]);
    }

    /**
     * Set default payment method
     */
    public function setDefault(Request $request, string $id)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $paymentMethod = $this->paymentMethodService->setDefault($user, $id);

        return ResponseService::response([
            'success' => true,
            'data' => $paymentMethod,
            'message' => 'Default payment method updated',
            'status' => 200,
        ]);
    }

    /**
     * Delete payment method
     */
    public function destroy(string $id)
    {
        $user = User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $this->paymentMethodService->delete($user, $id);

        return ResponseService::response([
            'success' => true,
            'message' => 'Payment method deleted successfully',
            'status' => 200,
        ]);
    }
}
