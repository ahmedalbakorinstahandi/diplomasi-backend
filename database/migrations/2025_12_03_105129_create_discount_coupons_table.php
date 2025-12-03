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

        Schema::create('discount_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->comment('UNIQUE');
            $table->string('description', 255)->nullable();
            $table->integer('percentage');
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_by_user')->nullable();
            $table->integer('used_count')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('discount_type', ["fixed","percentage"]);
            $table->decimal('discount_value', 10, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'deleted_at']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_coupons');
    }
};
