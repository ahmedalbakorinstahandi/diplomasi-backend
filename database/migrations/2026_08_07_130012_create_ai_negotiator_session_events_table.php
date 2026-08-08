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

        Schema::create('ai_negotiator_session_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->foreign('ai_negotiator_session_id', 'ain_events_session_fk')
                ->references('id')->on('ai_negotiator_sessions')
                ->cascadeOnDelete();
            $table->enum('from_state', [
                'intake',
                'simulating',
                'evaluating',
                'completed',
                'abandoned',
            ])->nullable();
            $table->enum('to_state', [
                'intake',
                'simulating',
                'evaluating',
                'completed',
                'abandoned',
            ]);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ai_negotiator_session_id', 'created_at'], 'ain_events_session_at_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_session_events');
    }
};
