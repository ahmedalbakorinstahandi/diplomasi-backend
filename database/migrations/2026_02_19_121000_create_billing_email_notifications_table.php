<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_email_notifications')) {
            return;
        }

        Schema::create('billing_email_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 50);
            $table->string('to_email', 191);
            $table->string('subject', 255);
            $table->longText('content');
            $table->json('attachments')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('send_at');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at'], 'ben_status_sendat_idx');
            $table->index(['user_id', 'type'], 'ben_user_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_email_notifications');
    }
};

