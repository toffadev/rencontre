<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('profile_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Un utilisateur ne peut signaler un même profil qu'une seule fois
            $table->unique(['reporter_id', 'reported_user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('profile_reports');
    }
};
