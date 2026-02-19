<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->uuid('merchant_reference_id');
            $table->uuid('given_id');
            $table->string('provider', 30)->default('moyasar');
            $table->string('provider_payment_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->unsignedInteger('attempt_no')->default(1);
            $table->date('billing_period_start')->nullable();
            $table->date('billing_period_end')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('gateway_status', 30)->nullable();
            $table->text('redirect_url')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique('merchant_reference_id');
            $table->unique('given_id');
            $table->unique(['provider', 'provider_payment_id']);

            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
            $table->index(['subscription_id']);
            $table->index(['provider', 'status']);
            $table->index(['next_retry_at']);
            $table->index(['billing_period_start', 'billing_period_end']);
            $table->index(['finalized_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

