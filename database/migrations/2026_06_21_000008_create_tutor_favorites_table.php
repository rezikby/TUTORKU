<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000008_create_tutor_favorites_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar tutor favorit milik siswa. Dipakai di menu "Favorit" pada
     * Dashboard Siswa dan widget "Total Tutor Favorit".
     */
    public function up(): void
    {
        Schema::create('tutor_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tutor_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_favorites');
    }
};
