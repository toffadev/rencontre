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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('content');
            $table->boolean('is_from_client')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Index pour la recherche rapide des conversations
            $table->index(['client_id', 'profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
