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

        Schema::create('scenario_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scenario_id');
            $table->foreign('scenario_id')->references('id')->on('scenarios');
            $table->string('code', 20);
            $table->enum('type', ["single_choice", "true_false"]);
            $table->text('question_text');
            $table->string('attached_path', 100)->nullable();
            $table->text('explanation')->nullable()->comment('شرح بعد الإجابة (تعليمي)؛');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['scenario_id', 'order_index', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_questions');
    }
};
