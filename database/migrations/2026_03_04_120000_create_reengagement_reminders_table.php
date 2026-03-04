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
        Schema::create('reengagement_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('amount')->comment('عدد الوحدات (أيام/أسابيع/أشهر/سنوات)');
            $table->string('unit', 10)->comment('day|week|month|year');
            $table->string('title', 255);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reengagement_reminders');
    }
};
