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
            $table->decimal('progress_percentage', 5, 2)->default(0)->after('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_lesson_attempts', function (Blueprint $table) {
            $table->dropColumn('progress_percentage');
        });
    }
};
