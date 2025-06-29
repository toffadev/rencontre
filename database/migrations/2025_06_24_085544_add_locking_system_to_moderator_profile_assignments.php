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
        Schema::table('moderator_profile_assignments', function (Blueprint $table) {
            $table->json('locked_clients')->nullable()->after('active_conversations_count');
            $table->integer('queue_position')->nullable()->after('locked_clients');
            $table->timestamp('assigned_at')->nullable()->after('queue_position');
            $table->timestamp('last_activity_check')->nullable()->after('last_typing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderator_profile_assignments', function (Blueprint $table) {
            $table->dropColumn('locked_clients');
            $table->dropColumn('queue_position');
            $table->dropColumn('assigned_at');
            $table->dropColumn('last_activity_check');
        });
    }
};
