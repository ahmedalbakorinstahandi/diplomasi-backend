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

        Schema::create('user_negotiation_situation_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->foreign('negotiation_situation_id')->references('id')->on('negotiation_situations');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->enum('track_status', ['locked', 'open', 'completed'])->default('locked');
            $table->boolean('is_completed')->default(false);
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->unique(['user_id', 'negotiation_situation_id', 'deleted_at']);
            $table->index(['user_id', 'negotiation_situation_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_negotiation_situation_progress');
    }
};
