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

        Schema::create('ai_negotiator_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'ain_credit_tx_user_fk')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->unsignedBigInteger('ai_negotiator_session_id')->nullable();
            $table->foreign('ai_negotiator_session_id', 'ain_credit_tx_session_fk')
                ->references('id')->on('ai_negotiator_sessions')
                ->nullOnDelete();
            $table->enum('type', ['consume', 'refill', 'adjust', 'expire']);
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at'], 'ain_credit_tx_user_at_idx');
            $table->index(['ai_negotiator_session_id'], 'ain_credit_tx_session_idx');
            // Double-charge protection: one consume (and at most one row per type) per session.
            // Refill/adjust/expire use session_id = NULL; MySQL allows multiple NULLs in UNIQUE.
            $table->unique(
                ['ai_negotiator_session_id', 'type'],
                'ain_credit_tx_session_type_uq'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_credit_transactions');
    }
};
