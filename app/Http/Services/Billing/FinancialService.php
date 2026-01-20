<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\FinancialTransaction;
use App\Models\Billing\Subscription;
use App\Services\FilterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    /**
     * Record a financial transaction
     */
    public function recordTransaction(array $data): FinancialTransaction
    {
        return FinancialTransaction::create($data);
    }

    /**
     * Get financial overview
     */
    public function getOverview($dateRange = null)
    {
        $query = FinancialTransaction::query();

        if ($dateRange) {
            $startDate = $dateRange['start_date'] ?? Carbon::now()->startOfMonth();
            $endDate = $dateRange['end_date'] ?? Carbon::now()->endOfMonth();
            $query->byDateRange($startDate, $endDate);
        }

        $totalRevenue = (clone $query)->completed()->byType('subscription_payment')->sum('amount');
        $totalUpgrades = (clone $query)->completed()->byType('upgrade_payment')->sum('amount');
        $totalRefunds = (clone $query)->byType('refund')->sum('amount');
        $totalTransactions = (clone $query)->count();
        $pendingTransactions = (clone $query)->pending()->count();
        $failedTransactions = (clone $query)->failed()->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_upgrades' => $totalUpgrades,
            'total_refunds' => $totalRefunds,
            'net_revenue' => $totalRevenue + $totalUpgrades - $totalRefunds,
            'total_transactions' => $totalTransactions,
            'pending_transactions' => $pendingTransactions,
            'failed_transactions' => $failedTransactions,
        ];
    }

    /**
     * Get revenue breakdown
     */
    public function getRevenue($dateRange = null, $groupBy = 'day')
    {
        $query = FinancialTransaction::query()
            ->completed()
            ->whereIn('type', ['subscription_payment', 'upgrade_payment']);

        if ($dateRange) {
            $startDate = $dateRange['start_date'] ?? Carbon::now()->startOfMonth();
            $endDate = $dateRange['end_date'] ?? Carbon::now()->endOfMonth();
            $query->byDateRange($startDate, $endDate);
        }

        $format = match ($groupBy) {
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            'year' => 'Y',
            default => 'Y-m-d',
        };

        return $query
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$format}') as period"),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    /**
     * Get transactions list
     */
    public function getTransactions($filters = [])
    {
        $query = FinancialTransaction::query()->with(['user', 'subscription', 'subscriptionEvent']);

        $filters['per_page'] = $filters['per_page'] ?? 20;
        $filters['sort_field'] = $filters['sort_field'] ?? 'created_at';
        $filters['sort_order'] = $filters['sort_order'] ?? 'desc';

        $searchFields = ['description'];
        $numericFields = ['amount'];
        $dateFields = ['created_at', 'processed_at'];
        $exactMatchFields = ['type', 'status', 'user_id', 'subscription_id'];
        $inFields = ['type', 'status'];

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    /**
     * Get subscription statistics
     */
    public function getSubscriptionStats()
    {
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->count();

        $expiredSubscriptions = Subscription::where('status', 'expired')
            ->orWhere(function ($query) {
                $query->where('status', 'active')
                    ->where('end_date', '<', now()->toDateString());
            })
            ->count();

        $cancelledSubscriptions = Subscription::where('status', 'cancelled')->count();

        $totalSubscriptions = Subscription::count();

        $monthlyRevenue = FinancialTransaction::completed()
            ->whereIn('type', ['subscription_payment', 'upgrade_payment'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return [
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => $expiredSubscriptions,
            'cancelled_subscriptions' => $cancelledSubscriptions,
            'total_subscriptions' => $totalSubscriptions,
            'monthly_revenue' => $monthlyRevenue,
        ];
    }
}
