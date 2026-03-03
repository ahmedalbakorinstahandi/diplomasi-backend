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
        if (Schema::hasColumn('plans', 'stripe_plan_id')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropUnique(['stripe_plan_id', 'deleted_at']);
                $table->dropColumn('stripe_plan_id');
                $table->unique(['name', 'interval', 'deleted_at']);
            });
        }

        if (Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropUnique(['stripe_subscription_id', 'deleted_at']);
                $table->dropColumn('stripe_subscription_id');
            });
        }

        $stripeColumns = [
            'stripe_invoice_id',
            'stripe_payment_intent_id',
            'stripe_charge_id',
            'stripe_event_id',
        ];
        foreach ($stripeColumns as $column) {
            if (Schema::hasColumn('subscription_events', $column)) {
                Schema::table('subscription_events', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['name', 'interval', 'deleted_at']);
            $table->string('stripe_plan_id', 100)->nullable()->after('name');
            $table->unique(['stripe_plan_id', 'deleted_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id', 100)->nullable()->after('currency');
            $table->unique(['stripe_subscription_id', 'deleted_at']);
        });

        Schema::table('subscription_events', function (Blueprint $table) {
            $table->string('stripe_invoice_id', 100)->nullable()->after('currency');
            $table->string('stripe_payment_intent_id', 100)->nullable()->after('stripe_invoice_id');
            $table->string('stripe_charge_id', 100)->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_event_id', 255)->nullable()->after('stripe_charge_id');
        });
    }
};
