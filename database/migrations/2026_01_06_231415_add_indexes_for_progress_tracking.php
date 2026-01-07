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
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->index(['user_id', 'lesson_id'], 'idx_user_lesson_progress_user_lesson');
            $table->index(['user_id', 'is_completed'], 'idx_user_lesson_progress_user_completed');
        });

        Schema::table('user_lesson_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'lesson_id', 'status'], 'idx_user_lesson_attempts_user_lesson_status');
        });

        Schema::table('user_scenario_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'scenario_id', 'status'], 'idx_user_scenario_attempts_user_scenario_status');
            $table->index(['user_id', 'scenario_id', 'started_at'], 'idx_user_scenario_attempts_user_scenario_started');
            $table->index(['user_id', 'is_completed'], 'idx_user_scenario_attempts_user_completed');
        });

        Schema::table('level_tracks', function (Blueprint $table) {
            $table->index(['level_id', 'order_index'], 'idx_level_tracks_level_order');
            $table->index(['trackable_id', 'trackable_type'], 'idx_level_tracks_trackable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->dropIndex('idx_user_lesson_progress_user_lesson');
            $table->dropIndex('idx_user_lesson_progress_user_completed');
        });

        Schema::table('user_lesson_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_user_lesson_attempts_user_lesson_status');
        });

        Schema::table('user_scenario_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_user_scenario_attempts_user_scenario_status');
            $table->dropIndex('idx_user_scenario_attempts_user_scenario_started');
            $table->dropIndex('idx_user_scenario_attempts_user_completed');
        });

        Schema::table('level_tracks', function (Blueprint $table) {
            $table->dropIndex('idx_level_tracks_level_order');
            $table->dropIndex('idx_level_tracks_trackable');
        });
    }
};
