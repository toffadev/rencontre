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
        Schema::create('client_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('locked_at')->useCurrent(); // Modifié ici
            $table->timestamp('expires_at')->nullable(); // Modifié ici
            $table->string('lock_reason')->default('conversation'); // 'conversation', 'admin', 'manual'
            $table->timestamps();
            $table->softDeletes(); // Pour garder l'historique des verrous

            // Index pour l'optimisation des requêtes
            $table->index(['client_id', 'profile_id', 'expires_at']);
            $table->index(['moderator_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_locks');
    }
};
