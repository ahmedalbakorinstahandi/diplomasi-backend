<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align Moyasar/billing default currency with MOYASAR_CURRENCY=USD.
     */
    public function up(): void
    {
        if (! Schema::hasTable('refund_transactions')) {
            return;
        }

        DB::statement("ALTER TABLE refund_transactions MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'USD'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('refund_transactions')) {
            return;
        }

        DB::statement("ALTER TABLE refund_transactions MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'SAR'");
    }
};
