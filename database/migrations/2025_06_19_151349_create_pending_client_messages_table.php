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
        Schema::create('pending_client_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->timestamp('pending_since');
            $table->boolean('is_notified')->default(false);
            $table->boolean('is_processed')->default(false);
            $table->timestamps();

            // Index combiné pour optimiser les recherches de messages en attente
            $table->index(
                ['is_processed', 'is_notified', 'pending_since'],
                'pending_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_client_messages');
    }
};
