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

        Schema::create('user_scenario_question_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id');
            $table->foreign('question_id')->references('id')->on('ScenarioQuestions');
            $table->unsignedBigInteger('attempt_id');
            $table->foreign('attempt_id')->references('id')->on('UserScenarioAttempts');
            $table->integer('step_index');
            $table->timestamp('answered_at')->nullable();
            $table->integer('time_spent')->nullable();
            $table->softDeletes();
            $table->index(['attempt_id', 'step_index']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_scenario_question_answers');
    }
};
