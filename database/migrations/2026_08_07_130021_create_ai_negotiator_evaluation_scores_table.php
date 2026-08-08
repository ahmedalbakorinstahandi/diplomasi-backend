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

        Schema::create('ai_negotiator_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_evaluation_id');
            $table->foreign('ai_negotiator_evaluation_id', 'ain_eval_scores_eval_fk')
                ->references('id')->on('ai_negotiator_evaluations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('ai_negotiator_rubric_item_id');
            $table->foreign('ai_negotiator_rubric_item_id', 'ain_eval_scores_rubric_fk')
                ->references('id')->on('ai_negotiator_rubric_items')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('max_score');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['ai_negotiator_evaluation_id', 'ai_negotiator_rubric_item_id', 'deleted_at'],
                'ain_eval_scores_uq'
            );
            $table->index(['ai_negotiator_rubric_item_id'], 'ain_eval_scores_rubric_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_evaluation_scores');
    }
};
