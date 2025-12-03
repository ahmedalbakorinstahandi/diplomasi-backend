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

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('stripe_plan_id', 100);
            $table->decimal('price', 10, 2);
            $table->enum('interval', ["monthly","semi_annual","annual"]);
            $table->text('description')->nullable();
            $table->string('icon_url', 100);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['stripe_plan_id', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
