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

        Schema::create('ai_negotiator_response_library', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_knowledge_school_id')->nullable();
            $table->foreign('ai_negotiator_knowledge_school_id', 'ain_resp_lib_school_fk')
                ->references('id')->on('ai_negotiator_knowledge_schools')
                ->nullOnDelete();
            $table->string('name', 255)->nullable();
            $table->text('response_text');
            $table->enum('tone', [
                'firm',
                'diplomatic',
                'soft',
                'smart_question',
                'reframe',
                'polite_refusal',
                'de_escalation',
                'closing',
                'counter_objection',
                'clarification',
                'no_concession_without_return',
            ]);
            $table->string('situation_type', 50)->nullable();
            $table->string('objection_type', 50)->nullable();
            $table->string('category', 100)->nullable();
            $table->text('when_to_use')->nullable();
            $table->text('when_not_to_use')->nullable();
            $table->enum('risk', ['low', 'medium', 'high'])->nullable();
            $table->enum('difficulty', ['easy', 'realistic', 'hard', 'expert', 'ruthless'])->nullable();
            $table->text('example')->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(
                ['tone', 'situation_type', 'is_published', 'deleted_at'],
                'ain_resp_lib_tone_sit_idx'
            );
            $table->index(
                ['objection_type', 'is_published', 'deleted_at'],
                'ain_resp_lib_obj_idx'
            );
            $table->index(
                ['ai_negotiator_knowledge_school_id', 'deleted_at'],
                'ain_resp_lib_school_idx'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_response_library');
    }
};
