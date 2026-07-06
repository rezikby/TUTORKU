<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            return;
        }

        $hasUserOneFk = DB::selectOne(
            "SELECT COUNT(*) AS count FROM information_schema.REFERENTIAL_CONSTRAINTS " .
            "WHERE CONSTRAINT_SCHEMA = DATABASE() " .
            "AND TABLE_NAME = 'chat_conversations' " .
            "AND CONSTRAINT_NAME = 'chat_conversations_user_one_id_foreign'"
        );

        if ($hasUserOneFk && $hasUserOneFk->count > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP FOREIGN KEY chat_conversations_user_one_id_foreign');
        }

        $hasUserTwoFk = DB::selectOne(
            "SELECT COUNT(*) AS count FROM information_schema.REFERENTIAL_CONSTRAINTS " .
            "WHERE CONSTRAINT_SCHEMA = DATABASE() " .
            "AND TABLE_NAME = 'chat_conversations' " .
            "AND CONSTRAINT_NAME = 'chat_conversations_user_two_id_foreign'"
        );

        if ($hasUserTwoFk && $hasUserTwoFk->count > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP FOREIGN KEY chat_conversations_user_two_id_foreign');
        }

        DB::statement(
            'ALTER TABLE chat_conversations ADD CONSTRAINT chat_conversations_user_one_id_foreign ' .
            'FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE'
        );

        DB::statement(
            'ALTER TABLE chat_conversations ADD CONSTRAINT chat_conversations_user_two_id_foreign ' .
            'FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            return;
        }

        $hasUserOneFk = DB::selectOne(
            "SELECT COUNT(*) AS count FROM information_schema.REFERENTIAL_CONSTRAINTS " .
            "WHERE CONSTRAINT_SCHEMA = DATABASE() " .
            "AND TABLE_NAME = 'chat_conversations' " .
            "AND CONSTRAINT_NAME = 'chat_conversations_user_one_id_foreign'"
        );

        if ($hasUserOneFk && $hasUserOneFk->count > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP FOREIGN KEY chat_conversations_user_one_id_foreign');
        }

        $hasUserTwoFk = DB::selectOne(
            "SELECT COUNT(*) AS count FROM information_schema.REFERENTIAL_CONSTRAINTS " .
            "WHERE CONSTRAINT_SCHEMA = DATABASE() " .
            "AND TABLE_NAME = 'chat_conversations' " .
            "AND CONSTRAINT_NAME = 'chat_conversations_user_two_id_foreign'"
        );

        if ($hasUserTwoFk && $hasUserTwoFk->count > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP FOREIGN KEY chat_conversations_user_two_id_foreign');
        }
    }
};
