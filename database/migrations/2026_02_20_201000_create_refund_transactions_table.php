<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refund_transactions')) {
            return;
        }

        Schema::create('refund_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->string('provider', 30)->default('moyasar');
            $table->string('provider_payment_id', 191);
            $table->string('provider_refund_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('SAR');
            $table->string('status', 30)->default('pending'); // pending|processing|completed|failed
            $table->string('gateway_status', 30)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_transaction_id', 'status'], 'rt_payment_status_idx');
            $table->index(['provider', 'provider_payment_id'], 'rt_provider_payment_idx');
            $table->index(['provider_refund_id'], 'rt_provider_refund_idx');
            $table->index(['refunded_at'], 'rt_refunded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
    }
};
