<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * true = تظهر في القائمة حتى بعد القراءة (الافتراضي)
     * false = تظهر فقط غير المقروءة، فبعد القراءة تختفي من القائمة (مثل التذكيرات)
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('show_after_read')->default(true)->after('read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('show_after_read');
        });
    }
};
