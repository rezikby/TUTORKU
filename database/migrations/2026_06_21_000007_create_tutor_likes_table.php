<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000007_create_tutor_likes_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mencatat like/dislike per (user, tutor) supaya 1 user hanya bisa
     * memberi 1 vote (like ATAU dislike) per tutor, dan bisa diubah/dibatalkan.
     * Agregat like_count/dislike_count di tutor_profiles di-refresh dari tabel ini.
     */
    public function up(): void
    {
        Schema::create('tutor_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['like', 'dislike']);
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_likes');
    }
};
