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
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('profile_id')->constrained('profiles');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->boolean('has_been_opened')->default(false);
            $table->boolean('awaiting_reply')->default(false);
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['client_id', 'profile_id']);
            $table->unique(['client_id', 'profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
