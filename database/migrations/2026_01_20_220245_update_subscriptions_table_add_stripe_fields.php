<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_customer_id', 100)->nullable()->after('stripe_subscription_id');
            $table->string('stripe_payment_method_id', 100)->nullable()->after('stripe_customer_id');
            $table->boolean('cancel_at_period_end')->default(false)->after('auto_renew');
            $table->timestamp('canceled_at')->nullable()->after('cancel_at_period_end');
            $table->timestamp('trial_ends_at')->nullable()->after('canceled_at');
            $table->date('current_period_start')->nullable()->after('trial_ends_at');
            $table->date('current_period_end')->nullable()->after('current_period_start');
            $table->index(['stripe_customer_id', 'status', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_payment_method_id',
                'cancel_at_period_end',
                'canceled_at',
                'trial_ends_at',
                'current_period_start',
                'current_period_end',
            ]);
        });
    }
};
