<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align refund_transactions default currency with SAR-only gateway.
     */
    public function up(): void
    {
        if (!Schema::hasTable('refund_transactions')) {
            return;
        }

        DB::statement("ALTER TABLE refund_transactions MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'SAR'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('refund_transactions')) {
            return;
        }

        DB::statement("ALTER TABLE refund_transactions MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'USD'");
    }
};

