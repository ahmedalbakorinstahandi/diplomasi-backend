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

        Schema::create('lesson_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lesson_id');
            $table->foreign('lesson_id')->references('id')->on('lessons');
            $table->enum('type', ["single_choice","multiple_choice","true_false","match"]);
            $table->text('question_text');
            $table->string('attached_path', 100)->nullable();
            $table->text('explanation')->nullable()->comment('شرح بعد الإجابة (تعليمي)؛');
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lesson_id', 'order_index']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_questions');
    }
};
