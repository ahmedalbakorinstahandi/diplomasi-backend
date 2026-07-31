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

        Schema::create('user_negotiation_final_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('negotiation_level_id');
            $table->foreign('negotiation_level_id')->references('id')->on('negotiation_levels');
            $table->enum('status', ['in_progress', 'finished', 'abandoned'])->default('in_progress');
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedSmallInteger('total_questions')->default(15);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->softDeletes();
            $table->index(['user_id', 'negotiation_level_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_negotiation_final_test_attempts');
    }
};
