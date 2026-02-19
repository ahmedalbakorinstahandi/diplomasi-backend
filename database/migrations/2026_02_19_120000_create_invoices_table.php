<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->string('invoice_number', 64)->unique();
            $table->string('status', 30)->default('issued');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('payment_transaction_id', 'inv_payment_tx_uniq');
            $table->index(['user_id', 'issued_at'], 'inv_user_issued_idx');
            $table->index(['status'], 'inv_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

