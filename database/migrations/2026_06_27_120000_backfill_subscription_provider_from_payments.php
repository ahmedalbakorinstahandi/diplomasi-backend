<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('payment_transactions')) {
            return;
        }

        // Moyasar: linked payment on subscription
        $moyasarLinkedIds = DB::table('payment_transactions')
            ->select('subscription_id')
            ->whereNotNull('subscription_id')
            ->where('provider', 'moyasar')
            ->where('status', 'paid')
            ->distinct()
            ->pluck('subscription_id');

        if ($moyasarLinkedIds->isNotEmpty()) {
            DB::table('subscriptions')
                ->whereNull('provider')
                ->whereIn('id', $moyasarLinkedIds)
                ->update(['provider' => 'moyasar']);
        }

        // Moyasar: paid payment for same user + plan (first purchase before subscription_id was set)
        $moyasarPairs = DB::table('payment_transactions')
            ->select('user_id', 'plan_id')
            ->where('provider', 'moyasar')
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->distinct()
            ->get();

        foreach ($moyasarPairs as $pair) {
            DB::table('subscriptions')
                ->whereNull('provider')
                ->where('user_id', $pair->user_id)
                ->where('plan_id', $pair->plan_id)
                ->update(['provider' => 'moyasar']);
        }

        // Apple: linked payment
        $appleLinkedIds = DB::table('payment_transactions')
            ->select('subscription_id')
            ->whereNotNull('subscription_id')
            ->where('provider', 'apple')
            ->distinct()
            ->pluck('subscription_id');

        if ($appleLinkedIds->isNotEmpty()) {
            DB::table('subscriptions')
                ->whereNull('provider')
                ->whereIn('id', $appleLinkedIds)
                ->update(['provider' => 'apple']);
        }

        // Apple: IAP ownership record
        if (Schema::hasTable('apple_iap_subscription_ownerships')) {
            $ownershipPairs = DB::table('apple_iap_subscription_ownerships')
                ->select('user_id', 'plan_id')
                ->distinct()
                ->get();

            foreach ($ownershipPairs as $pair) {
                DB::table('subscriptions')
                    ->whereNull('provider')
                    ->where('user_id', $pair->user_id)
                    ->where('plan_id', $pair->plan_id)
                    ->update(['provider' => 'apple']);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: cannot reliably undo inferred values
    }
};
