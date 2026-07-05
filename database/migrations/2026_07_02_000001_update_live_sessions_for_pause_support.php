<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `live_sessions` MODIFY `status` ENUM('scheduled','ongoing','paused','ended') NOT NULL DEFAULT 'scheduled'");

        Schema::table('live_sessions', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable()->after('ended_at');
            $table->unsignedInteger('total_paused_seconds')->default(0)->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->enum('status', ['scheduled', 'ongoing', 'ended'])
                ->default('scheduled')
                ->index()
                ->change();
            $table->dropColumn('paused_at');
            $table->dropColumn('total_paused_seconds');
        });
    }
};
