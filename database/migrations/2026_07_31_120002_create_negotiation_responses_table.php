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

        Schema::create('negotiation_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->foreign('negotiation_situation_id', 'neg_responses_situation_fk')
                ->references('id')->on('negotiation_situations');
            $table->enum('style', ['gentle', 'diplomatic', 'firm']);
            $table->text('response_text');
            $table->text('explanation');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['negotiation_situation_id', 'style', 'deleted_at'], 'neg_responses_unique');
            $table->index(['negotiation_situation_id', 'deleted_at'], 'neg_responses_situation_idx');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('negotiation_responses');
    }
};
