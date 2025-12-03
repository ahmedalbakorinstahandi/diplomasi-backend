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

        Schema::create('user_scenario_answer_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_answer_id');
            $table->foreign('user_answer_id')->references('id')->on('UserScenarioQuestionAnswers');
            $table->unsignedBigInteger('option_id');
            $table->foreign('option_id')->references('id')->on('ScenarioQuestionOptions');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->softDeletes();
            $table->unique(['user_answer_id', 'option_id', 'deleted_at']);
            $table->index(['user_answer_id', 'option_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_scenario_answer_options');
    }
};
