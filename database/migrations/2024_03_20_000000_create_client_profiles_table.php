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
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('sexual_orientation', ['heterosexual', 'homosexual'])->nullable();
            $table->enum('seeking_gender', ['male', 'female'])->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('France');
            $table->enum('relationship_status', ['single', 'divorced', 'widowed'])->nullable();
            $table->integer('height')->nullable(); // en cm
            $table->string('occupation')->nullable();
            $table->boolean('has_children')->default(false);
            $table->boolean('wants_children')->nullable();
            $table->boolean('profile_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
