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
        Schema::create('profile_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('locked_at')->useCurrent(); // Ajout de useCurrent()
            $table->timestamp('expires_at')->nullable(); // Rendue nullable
            $table->string('lock_type')->default('system'); // 'system', 'assignment', 'manual'
            $table->timestamps();
            $table->softDeletes(); // Pour garder l'historique des verrous

            // Index pour l'optimisation des requêtes
            $table->index(['profile_id', 'expires_at']);
            $table->index(['moderator_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_locks');
    }
};
