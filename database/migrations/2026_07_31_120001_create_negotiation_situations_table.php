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

        // Recover if a prior run created the table then failed on the long auto index name.
        Schema::dropIfExists('negotiation_situations');

        Schema::create('negotiation_situations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_level_id');
            $table->foreign('negotiation_level_id', 'neg_situations_level_fk')
                ->references('id')->on('negotiation_levels');
            $table->text('prompt_text');
            $table->enum('prompt_type', ['quote', 'scene'])->default('quote');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_free');
            $table->timestamps();
            $table->softDeletes();
            // MySQL identifier limit is 64 chars — keep names short.
            $table->index(
                ['negotiation_level_id', 'is_published', 'order_index', 'deleted_at'],
                'neg_situations_level_pub_ord_idx'
            );
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negotiation_situations');
    }
};
