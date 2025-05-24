<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('age')->nullable();
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();
            $table->string('profession')->nullable();
            $table->enum('celibataire', ['oui', 'non'])->nullable();
            $table->enum('situation_residence', ['seul', 'colocation', 'famille', 'autre'])->nullable();
            $table->enum('orientation', ['heterosexuel', 'homosexuel', 'bisexuel'])->nullable();
            $table->string('loisirs')->nullable();
            $table->string('preference_negative')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_infos');
    }
};
