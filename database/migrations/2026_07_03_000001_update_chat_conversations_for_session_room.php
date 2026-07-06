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
        
        // Drop the old unique index
        DB::statement('ALTER TABLE chat_conversations DROP INDEX chat_conversations_user_one_id_user_two_id_unique');
        
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
        
        DB::statement('ALTER TABLE chat_conversations DROP INDEX chat_conversations_user_one_two_booking_unique');
        
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->unique(['user_one_id', 'user_two_id']);
        });
        
        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
