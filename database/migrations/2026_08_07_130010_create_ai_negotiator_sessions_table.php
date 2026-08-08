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

        Schema::create('ai_negotiator_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'ain_sessions_user_fk')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->enum('session_type', ['practice', 'prepare', 'analyze'])->default('practice');
            $table->enum('session_state', [
                'intake',
                'simulating',
                'evaluating',
                'completed',
                'abandoned',
            ])->default('intake');
            $table->enum('difficulty', [
                'easy',
                'realistic',
                'hard',
                'expert',
                'ruthless',
            ])->default('realistic');
            $table->enum('training_mode', [
                'realistic',
                'stop_on_mistake',
                'short',
                'challenge',
            ])->default('realistic');
            $table->string('situation_type', 50)->nullable();
            $table->json('intake_data')->nullable();
            $table->json('opponent_persona')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('simulating_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'session_state', 'deleted_at'], 'ain_sessions_user_state_idx');
            $table->index(['user_id', 'created_at'], 'ain_sessions_user_created_idx');
        });

        // MySQL only: generated is_active + one-active-session unique.
        // SQLite (tests) skips these; SessionService will enforce the invariant.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('ai_negotiator_sessions', function (Blueprint $table) {
                $table->tinyInteger('is_active')
                    ->nullable()
                    ->storedAs(
                        "CASE WHEN `deleted_at` IS NULL AND `session_state` IN ('intake', 'simulating', 'evaluating') THEN 1 ELSE NULL END"
                    );
                $table->unique(['user_id', 'is_active'], 'ain_sessions_one_active_uq');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_sessions');
    }
};
