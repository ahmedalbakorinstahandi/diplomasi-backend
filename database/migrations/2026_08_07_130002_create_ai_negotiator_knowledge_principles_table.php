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

        Schema::create('ai_negotiator_knowledge_principles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->string('category', 100)->nullable();
            $table->text('description');
            $table->text('when_to_use')->nullable();
            $table->text('when_not_to_use')->nullable();
            $table->text('example')->nullable();
            $table->enum('difficulty', ['easy', 'realistic', 'hard', 'expert', 'ruthless'])->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'deleted_at'], 'ain_principles_code_uq');
            $table->index(['is_published', 'order_index', 'deleted_at'], 'ain_principles_pub_ord_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_negotiator_knowledge_principles');
    }
};
