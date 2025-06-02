<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['message_sent']);
            //$table->enum('type', ['message_sent', 'bonus_admin']);
            $table->integer('points_spent');
            $table->string('description')->nullable();
            $table->morphs('consumable'); // Pour lier à différents types (messages, etc.)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_consumptions');
    }
};
