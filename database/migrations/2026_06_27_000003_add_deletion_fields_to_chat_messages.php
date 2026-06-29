<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('read_at');
            $table->enum('deleted_for', ['me', 'all'])->nullable()->after('is_deleted');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('deleted_for');
            $table->timestamp('deleted_at')->nullable()->after('deleted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'deleted_for', 'deleted_by_user_id', 'deleted_at']);
        });
    }
};
