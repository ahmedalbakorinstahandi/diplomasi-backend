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

        Schema::create('user_lesson_question_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attempt_id');
            $table->foreign('attempt_id')->references('id')->on('user_lesson_attempts');
            $table->unsignedBigInteger('question_id')->index();
            $table->foreign('question_id')->references('id')->on('lesson_questions');
            $table->unsignedInteger('step_index');
            $table->tinyInteger('is_correct')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedInteger('time_spent')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['attempt_id', 'step_index'], 'user_lesson_question_answers_index');
            $table->unique(['attempt_id', 'question_id', 'deleted_at'], 'user_lesson_question_answers_unique');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_lesson_question_answers');
    }
};
