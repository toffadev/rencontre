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
        Schema::create('moderator_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('queued_at');
            $table->integer('priority')->default(0);
            $table->integer('position')->default(0);
            $table->float('estimated_wait_time')->default(5); // en minutes
            $table->timestamps();

            // Index pour l'optimisation des requêtes
            $table->index(['position', 'priority']);
            $table->unique('moderator_id'); // Un modérateur ne peut être qu'une fois dans la file
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderator_queue');
    }
};
