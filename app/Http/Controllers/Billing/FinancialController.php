<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Permissions\Billing\SubscriptionPermission;
use App\Http\Services\Billing\FinancialService;
use App\Services\ResponseService;
use Illuminate\Http\Request;

class FinancialController extends Controller
{
    protected $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Get financial overview
     */
    public function overview(Request $request)
    {
        SubscriptionPermission::canView();

        $dateRange = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateRange = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ];
        }

        $overview = $this->financialService->getOverview($dateRange);

        return ResponseService::response([
            'success' => true,
            'data' => $overview,
            'status' => 200,
        ]);
    }

    /**
     * Get revenue breakdown
     */
    public function revenue(Request $request)
    {
        SubscriptionPermission::canView();

        $dateRange = null;
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateRange = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ];
        }

        $groupBy = $request->input('group_by', 'day');

        $revenue = $this->financialService->getRevenue($dateRange, $groupBy);

        return ResponseService::response([
            'success' => true,
            'data' => $revenue,
            'status' => 200,
        ]);
    }

    /**
     * Get transactions list
     */
    public function transactions(Request $request)
    {
        SubscriptionPermission::canView();

        $transactions = $this->financialService->getTransactions($request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $transactions,
            'meta' => true,
            'status' => 200,
        ]);
    }

    /**
     * Get subscription statistics
     */
    public function subscriptionsStats()
    {
        SubscriptionPermission::canView();

        $stats = $this->financialService->getSubscriptionStats();

        return ResponseService::response([
            'success' => true,
            'data' => $stats,
            'status' => 200,
        ]);
    }

    /**
     * Get user payments/transactions (User route)
     */
    public function getUserPayments(Request $request)
    {
        $user = \App\Models\Users\User::auth();
        if (!$user) {
            return ResponseService::response([
                'success' => false,
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
        }

        $transactions = $this->financialService->getUserTransactions($user, $request->all());

        return ResponseService::response([
            'success' => true,
            'data' => $transactions,
            'meta' => true,
            'status' => 200,
        ]);
    }
}
