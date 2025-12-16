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

        Schema::create('lesson_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('lesson_questions');
            $table->text('option_text');
            $table->string('pair_key', 100)->nullable();
            $table->tinyInteger('is_correct')->nullable();
            $table->string('attached_path', 100)->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['question_id', 'order_index']);
            $table->unique(['question_id', 'pair_key', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_question_options');
    }
};
