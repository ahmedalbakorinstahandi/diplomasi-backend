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

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_id');
            $table->foreign('level_id')->references('id')->on('levels');
            $table->string('lesson_number');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('video_url', 255);
            $table->text('content');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['level_id', 'lesson_number', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
