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
        Schema::table('user_lesson_attempts', function (Blueprint $table) {
            $table->boolean('video_watched')->default(false)->after('status');
            $table->timestamp('video_watched_at')->nullable()->after('video_watched');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_lesson_attempts', function (Blueprint $table) {
            $table->dropColumn(['video_watched', 'video_watched_at']);
        });
    }
};
