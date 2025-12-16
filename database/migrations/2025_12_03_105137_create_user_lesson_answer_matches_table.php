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

        Schema::create('user_lesson_answer_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_answer_id');
            $table->unsignedBigInteger('left_option_id');
            $table->foreign('left_option_id')->references('id')->on('lesson_question_options');
            $table->unsignedBigInteger('right_option_id');
            $table->foreign('right_option_id')->references('id')->on('lesson_question_options');
            $table->boolean('is_correct');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_lesson_answer_matches');
    }
};
