<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تحويل تواريخ الاشتراك والفواتير من تاريخ فقط إلى تاريخ ووقت (بالثانية)
     * لضمان احتساب المدد (شهر / 3 أشهر / 6 أشهر / سنة) بدقة.
     */
    public function up(): void
    {
        // subscriptions: start_date, end_date
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable()->change();
            $table->dateTime('end_date')->nullable()->change();
        });

        // subscription_events: start_date, end_date
        Schema::table('subscription_events', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable()->change();
            $table->dateTime('end_date')->nullable()->change();
        });

        // payment_transactions: billing_period_start, billing_period_end
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dateTime('billing_period_start')->nullable()->change();
            $table->dateTime('billing_period_end')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });

        Schema::table('subscription_events', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->date('billing_period_start')->nullable()->change();
            $table->date('billing_period_end')->nullable()->change();
        });
    }
};
