<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Apple IAP: ربط كل خطة بمنتج App Store (ios_product_id) وسعر iOS (ios_price).
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('ios_price', 10, 2)->nullable()->after('price');
            $table->string('ios_currency', 10)->nullable()->after('ios_price');
            $table->string('ios_product_id', 191)->nullable()->after('ios_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['ios_price', 'ios_currency', 'ios_product_id']);
        });
    }
};
