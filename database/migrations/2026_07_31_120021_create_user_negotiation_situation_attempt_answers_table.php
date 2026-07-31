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

        Schema::create('user_negotiation_situation_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_negotiation_situation_attempt_id');
            $table->foreign('user_negotiation_situation_attempt_id', 'unsa_answers_attempt_fk')
                ->references('id')->on('user_negotiation_situation_attempts');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->foreign('negotiation_situation_id', 'unsa_answers_sit_fk')
                ->references('id')->on('negotiation_situations');
            $table->enum('asked_style', ['gentle', 'diplomatic', 'firm']);
            $table->unsignedBigInteger('selected_negotiation_response_id')->nullable();
            $table->foreign('selected_negotiation_response_id', 'unsa_answers_response_fk')
                ->references('id')->on('negotiation_responses');
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->softDeletes();
            $table->index(['user_negotiation_situation_attempt_id', 'deleted_at'], 'unsa_answers_attempt_index');
            $table->unique(
                ['user_negotiation_situation_attempt_id', 'asked_style', 'deleted_at'],
                'unsa_answers_unique'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_negotiation_situation_attempt_answers');
    }
};
