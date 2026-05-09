<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->string('cover_image', 512)->nullable();
            $table->string('audio_url', 2048)->nullable();
            $table->string('audio_path', 512)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_free')->default(true);
            $table->boolean('requires_subscription')->default(false);
            $table->boolean('allow_download')->default(false);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['slug', 'deleted_at']);
            $table->index(['course_id', 'is_published', 'order_index']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('podcasts');
    }
};
