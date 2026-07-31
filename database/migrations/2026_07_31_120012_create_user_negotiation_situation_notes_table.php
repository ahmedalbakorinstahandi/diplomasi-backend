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

        Schema::create('user_negotiation_situation_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'unsn_user_fk')->references('id')->on('users');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->foreign('negotiation_situation_id', 'unsn_situation_fk')
                ->references('id')->on('negotiation_situations');
            $table->text('note_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'negotiation_situation_id', 'deleted_at'], 'unsn_user_sit_unique');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_negotiation_situation_notes');
    }
};
