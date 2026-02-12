<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove all Stripe/Geidea payment gateway fields and payment_attempts table.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop payment_attempts table (Geidea flow)
        Schema::dropIfExists('payment_attempts');

        // users: remove Stripe fields
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['stripe_customer_id', 'stripe_default_payment_method_id']);
            });
        }

        // plans: remove stripe_plan_id and its unique index
        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'stripe_plan_id')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropUnique(['stripe_plan_id', 'deleted_at']);
                $table->dropColumn('stripe_plan_id');
            });
        }

        // subscriptions: remove Stripe/period fields and indexes
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                    $table->dropUnique(['stripe_subscription_id', 'deleted_at']);
                    $table->dropColumn('stripe_subscription_id');
                }
                if (Schema::hasColumn('subscriptions', 'stripe_customer_id')) {
                    $table->dropIndex(['stripe_customer_id', 'status', 'deleted_at']);
                }
                $columnsToDrop = [
                    'stripe_customer_id',
                    'stripe_payment_method_id',
                    'cancel_at_period_end',
                    'canceled_at',
                    'trial_ends_at',
                    'current_period_start',
                    'current_period_end',
                ];
                $existing = array_filter($columnsToDrop, fn ($c) => Schema::hasColumn('subscriptions', $c));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        // subscription_events: remove Stripe fields
        if (Schema::hasTable('subscription_events')) {
            Schema::table('subscription_events', function (Blueprint $table) {
                $columnsToDrop = ['stripe_invoice_id', 'stripe_payment_intent_id', 'stripe_charge_id', 'stripe_event_id'];
                $existing = array_filter($columnsToDrop, fn ($c) => Schema::hasColumn('subscription_events', $c));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        // financial_transactions: remove Stripe fields
        if (Schema::hasTable('financial_transactions')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $columnsToDrop = ['stripe_payment_intent_id', 'stripe_invoice_id', 'stripe_charge_id'];
                $existing = array_filter($columnsToDrop, fn ($c) => Schema::hasColumn('financial_transactions', $c));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        // payment_methods: remove stripe_payment_method_id
        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'stripe_payment_method_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropUnique(['stripe_payment_method_id']);
                $table->dropColumn('stripe_payment_method_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restoring payment gateway schema is not implemented; run fresh migrations if needed.
    }
};
