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

        Schema::create('scenario_question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('scenario_questions');
            $table->text('option_text');
            $table->bigInteger('next_question_id')->nullable();
            $table->foreign('next_question_id')->references('id')->on('scenario_questions');
            $table->string('attached_path', 100)->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['question_id', 'order_index', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenario_question_options');
    }
};
