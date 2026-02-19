<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove Geidea payment artifacts safely.
     */
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $columnsToDrop = ['geidea_subscription_id', 'geidea_order_id'];
                $existing = array_filter($columnsToDrop, fn ($column) => Schema::hasColumn('subscriptions', $column));

                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (Schema::hasTable('payment_attempts')) {
            Schema::drop('payment_attempts');
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('subscriptions', 'geidea_subscription_id')) {
                    $table->string('geidea_subscription_id')->nullable()->after('canceled_at');
                }
                if (!Schema::hasColumn('subscriptions', 'geidea_order_id')) {
                    $table->string('geidea_order_id')->nullable()->after('geidea_subscription_id');
                }
            });
        }
    }
};
