<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('user_podcast_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('podcast_id')->constrained('podcasts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
            $table->unique(['user_id', 'podcast_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_podcast_favorites');
    }
};
