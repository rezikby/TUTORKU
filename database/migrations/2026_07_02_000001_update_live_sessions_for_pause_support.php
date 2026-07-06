<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom paused_at dan total_paused_seconds sudah ada di create_live_session_tables
        // Migration ini hanya untuk memastikan enum status include 'paused'
        DB::statement("ALTER TABLE `live_sessions` MODIFY `status` ENUM('scheduled','ongoing','paused','ended') NOT NULL DEFAULT 'scheduled'");
    }

    public function down(): void
    {
        // Rollback hanya enum status saja, jangan hapus kolom karena dibutuhkan di create migration
        DB::statement("ALTER TABLE `live_sessions` MODIFY `status` ENUM('scheduled','ongoing','ended') NOT NULL DEFAULT 'scheduled'");
    }
};
