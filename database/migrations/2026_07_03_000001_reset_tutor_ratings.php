<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reset semua rating dummy ke 0
        DB::table('tutor_profiles')->update([
            'rating_avg' => 0,
            'rating_count' => 0,
        ]);
    }

    public function down(): void
    {
        // Jika di-rollback, biarkan saja (tidak perlu restore)
    }
};
