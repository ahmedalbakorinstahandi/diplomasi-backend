<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Moyasar: linked payment on subscription
        DB::statement("
            UPDATE subscriptions s
            INNER JOIN (
                SELECT subscription_id, MAX(id) AS latest_id
                FROM payment_transactions
                WHERE subscription_id IS NOT NULL
                  AND provider = 'moyasar'
                  AND status = 'paid'
                GROUP BY subscription_id
            ) pt ON pt.subscription_id = s.id
            SET s.provider = 'moyasar'
            WHERE s.provider IS NULL
        ");

        // Moyasar: paid payment for same user + plan (first purchase before subscription_id was set)
        DB::statement("
            UPDATE subscriptions s
            INNER JOIN (
                SELECT user_id, plan_id, MAX(id) AS latest_id
                FROM payment_transactions
                WHERE provider = 'moyasar'
                  AND status = 'paid'
                  AND plan_id IS NOT NULL
                GROUP BY user_id, plan_id
            ) pt ON pt.user_id = s.user_id AND pt.plan_id = s.plan_id
            SET s.provider = 'moyasar'
            WHERE s.provider IS NULL
        ");

        // Apple: linked payment
        DB::statement("
            UPDATE subscriptions s
            INNER JOIN (
                SELECT subscription_id, MAX(id) AS latest_id
                FROM payment_transactions
                WHERE subscription_id IS NOT NULL
                  AND provider = 'apple'
                GROUP BY subscription_id
            ) pt ON pt.subscription_id = s.id
            SET s.provider = 'apple'
            WHERE s.provider IS NULL
        ");

        // Apple: IAP ownership record
        if (Schema::hasTable('apple_iap_subscription_ownerships')) {
            DB::statement("
                UPDATE subscriptions s
                INNER JOIN apple_iap_subscription_ownerships o
                    ON o.user_id = s.user_id AND o.plan_id = s.plan_id
                SET s.provider = 'apple'
                WHERE s.provider IS NULL
            ");
        }
    }

    public function down(): void
    {
        // Non-destructive: cannot reliably undo inferred values
    }
};
