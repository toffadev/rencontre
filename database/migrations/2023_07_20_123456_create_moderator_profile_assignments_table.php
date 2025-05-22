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
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();

            // Un modérateur ne peut avoir qu'un profil actif à la fois
            $table->unique(['user_id', 'is_active'], 'moderator_active_profile');

            // Un profil ne peut être attribué activement qu'à un seul modérateur à la fois
            $table->unique(['profile_id', 'is_active'], 'profile_active_moderator');
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
