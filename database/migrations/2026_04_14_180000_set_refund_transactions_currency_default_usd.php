<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align Moyasar/billing default currency with MOYASAR_CURRENCY=USD.
     */
    public function up(): void
    {
        $this->setCurrencyDefault('USD');
    }

    public function down(): void
    {
        $this->setCurrencyDefault('SAR');
    }

    private function setCurrencyDefault(string $currency): void
    {
        if (! Schema::hasTable('refund_transactions')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE refund_transactions MODIFY currency VARCHAR(3) NOT NULL DEFAULT '{$currency}'");

            return;
        }

        Schema::table('refund_transactions', function (Blueprint $table) use ($currency) {
            $table->string('currency', 3)->default($currency)->nullable(false)->change();
        });
    }
};
