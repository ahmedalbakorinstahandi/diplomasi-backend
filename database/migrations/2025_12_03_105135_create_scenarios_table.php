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

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_id');
            $table->foreign('level_id')->references('id')->on('levels');
            $table->text('title')->nullable();
            $table->json('description');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_free');
            $table->unsignedBigInteger('start_question_id')->nullable();
            $table->foreign('start_question_id')->references('id')->on('scenario_questions');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['level_id', 'is_published', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scenarios');
    }
};
