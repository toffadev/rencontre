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
        Schema::create('moderator_profile_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_exclusive')->default(false);
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();

            // Index pour l'optimisation des requêtes
            $table->index(['user_id', 'is_active']);
            $table->index(['profile_id', 'is_active']);

            // La contrainte unique a été supprimée car elle est gérée de manière programmatique
            // dans le modèle ModeratorProfileAssignment
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderator_profile_assignments');
    }
};
