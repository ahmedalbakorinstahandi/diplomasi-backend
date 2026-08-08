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

        Schema::create('ai_negotiator_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->foreign('ai_negotiator_session_id', 'ain_eval_session_fk')
                ->references('id')->on('ai_negotiator_sessions')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_score');
            $table->text('summary');
            $table->text('best_line')->nullable();
            $table->text('weakest_line')->nullable();
            $table->text('biggest_mistake')->nullable();
            $table->boolean('quick_concession')->default(false);
            $table->boolean('sensitive_info_leaked')->default(false);
            $table->boolean('good_questions')->default(false);
            $table->text('suggested_alternative_response')->nullable();
            $table->text('retry_exercise')->nullable();
            $table->enum('suggested_next_difficulty', [
                'easy',
                'realistic',
                'hard',
                'expert',
                'ruthless',
            ])->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['ai_negotiator_session_id', 'deleted_at'], 'ain_eval_session_uq');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_evaluations');
    }
};
