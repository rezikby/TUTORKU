<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->index('user_one_id', 'chat_conversations_user_one_id_index');
            $table->index('user_two_id', 'chat_conversations_user_two_id_index');
            $table->dropUnique(['user_one_id', 'user_two_id']);
            $table->unique(['user_one_id', 'user_two_id', 'booking_id'], 'chat_conversations_user_one_two_booking_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropUnique('chat_conversations_user_one_two_booking_unique');
            $table->dropIndex('chat_conversations_user_one_id_index');
            $table->dropIndex('chat_conversations_user_two_id_index');
            $table->unique(['user_one_id', 'user_two_id']);
        });
    }
};
