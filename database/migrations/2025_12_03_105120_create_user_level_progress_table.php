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

        Schema::create('user_level_progress', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->bigInteger('level_id');
            $table->foreign('level_id')->references('id')->on('levels');
            $table->unsignedBigInteger('current_lesson_id')->nullable();
            $table->foreign('current_lesson_id')->references('id')->on('lessons');
            $table->enum('status', ["not_started","in_progress","completed"]);
            $table->decimal('score', 6, 2);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->unique(['user_id', 'level_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_level_progress');
    }
};
