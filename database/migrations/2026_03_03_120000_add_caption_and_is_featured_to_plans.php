<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('caption', 255)->nullable()->after('description')->comment('عبارة ترويجية تعرض في التطبيق إن وُجدت');
            $table->boolean('is_featured')->default(false)->after('caption')->comment('عرض تاج/تمييز وبوردر برتقالي عند true');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['caption', 'is_featured']);
        });
    }
};
