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
        Schema::disableForeignKeyConstraints();

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('plan_id');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('restrict');
            $table->enum('type', ['subscription_create', 'subscription_upgrade'])->default('subscription_create');
            $table->string('merchant_reference', 255)->unique();
            $table->string('geidea_session_id', 255)->nullable();
            $table->string('geidea_order_id', 255)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('token_id', 255)->nullable(); // For tokenization
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('SAR');
            $table->enum('status', ['initiated', 'pending', 'verifying', 'completed', 'failed', 'canceled', 'expired'])->default('initiated');
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            $table->timestamp('verified_at')->nullable(); // When payment was verified via Geidea API
            $table->timestamp('expires_at')->nullable(); // Calculated locally, default +30 minutes
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['merchant_reference']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['geidea_order_id']);
            $table->index(['subscription_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
