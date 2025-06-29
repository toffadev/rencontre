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
            $table->boolean('is_currently_active')->default(false)->after('is_active');
            $table->integer('priority_score')->default(0)->after('is_exclusive');
            $table->json('conversation_ids')->nullable()->after('priority_score');

            $table->timestamp('last_message_sent')->nullable()->after('last_activity');
            $table->timestamp('last_typing')->nullable()->after('last_message_sent');
            $table->integer('active_conversations_count')->default(0)->after('last_typing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moderator_profile_assignments', function (Blueprint $table) {
            $table->dropColumn('is_currently_active');
            $table->dropColumn('priority_score');
            $table->dropColumn('conversation_ids');
            $table->dropColumn('last_message_sent');
            $table->dropColumn('last_typing');
            $table->dropColumn('active_conversations_count');
        });
    }
};
