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

        Schema::create('ai_negotiator_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->foreign('ai_negotiator_session_id', 'ain_messages_session_fk')
                ->references('id')->on('ai_negotiator_sessions')
                ->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->enum('type', ['text', 'audio'])->default('text');
            $table->text('content');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->enum('state_at_time', [
                'intake',
                'simulating',
                'evaluating',
                'completed',
                'abandoned',
            ]);
            $table->unsignedBigInteger('order_index');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['ai_negotiator_session_id', 'order_index', 'deleted_at'],
                'ain_messages_session_ord_uq'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_messages');
    }
};
