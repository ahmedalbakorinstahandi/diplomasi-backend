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
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('score');
            $table->enum('track_status', ['locked', 'open', 'completed'])->default('locked')->after('progress_percentage');
            $table->boolean('is_completed')->default(false)->after('track_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_lesson_progress', function (Blueprint $table) {
            $table->dropColumn(['progress_percentage', 'track_status', 'is_completed']);
        });
    }
};
