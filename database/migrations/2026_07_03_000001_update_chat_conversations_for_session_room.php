<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Temporarily disable foreign key checks to modify the unique constraint
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Drop the old unique index if it exists
        $oldIndex = DB::select(
            'SHOW INDEX FROM chat_conversations WHERE Key_name = ?',
            ['chat_conversations_user_one_id_user_two_id_unique']
        );
        if (count($oldIndex) > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP INDEX chat_conversations_user_one_id_user_two_id_unique');
        }
        
        // Add the new unique index with booking_id
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unique(['user_one_id', 'user_two_id', 'booking_id'], 'chat_conversations_user_one_two_booking_unique');
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Temporarily disable foreign key checks to modify the unique constraint
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $newIndex = DB::select(
            'SHOW INDEX FROM chat_conversations WHERE Key_name = ?',
            ['chat_conversations_user_one_two_booking_unique']
        );
        if (count($newIndex) > 0) {
            DB::statement('ALTER TABLE chat_conversations DROP INDEX chat_conversations_user_one_two_booking_unique');
        }

        // Remove duplicate conversation rows for the same user pair so the old unique index can be restored.
        DB::statement(
            'DELETE c1 FROM chat_conversations c1
             JOIN (
                 SELECT user_one_id, user_two_id, MIN(id) AS keep_id
                 FROM chat_conversations
                 GROUP BY user_one_id, user_two_id
                 HAVING COUNT(*) > 1
             ) c2 ON c1.user_one_id = c2.user_one_id
             AND c1.user_two_id = c2.user_two_id
             AND c1.id <> c2.keep_id'
        );
        
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unique(['user_one_id', 'user_two_id']);
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
