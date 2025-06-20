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
        Schema::create('moderator_notification_rounds', function (Blueprint $table) {
            $table->id();
            $table->integer('round_number');
            $table->json('moderator_ids_notified');
            $table->timestamp('sent_at');
            $table->integer('pending_messages_count');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderator_notification_rounds');
    }
};
