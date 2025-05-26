<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_client_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('last_moderator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('last_message_at')->nullable();
            $table->integer('total_messages')->default(0);
            $table->integer('total_points_received')->default(0);
            $table->timestamps();

            // Un client ne peut avoir qu'une seule entrée d'interaction par profil
            $table->unique(['profile_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_client_interactions');
    }
};
