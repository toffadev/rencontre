<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('points_amount');
            $table->decimal('money_amount', 10, 2);
            $table->string('stripe_payment_id')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_point_transactions');
    }
};
