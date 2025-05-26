<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('moderator_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->integer('short_messages_count')->default(0);
            $table->integer('long_messages_count')->default(0);
            $table->integer('points_received')->default(0);
            $table->decimal('earnings', 10, 2)->default(0);
            $table->date('stats_date');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'stats_date']);
            $table->index(['profile_id', 'stats_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('moderator_statistics');
    }
};
