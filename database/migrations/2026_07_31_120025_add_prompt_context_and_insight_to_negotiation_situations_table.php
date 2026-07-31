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
        Schema::table('negotiation_situations', function (Blueprint $table) {
            $table->text('prompt_context')->nullable()->after('prompt_text');
            $table->text('insight')->nullable()->after('prompt_context');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('negotiation_situations', function (Blueprint $table) {
            $table->dropColumn(['prompt_context', 'insight']);
        });
    }
};
