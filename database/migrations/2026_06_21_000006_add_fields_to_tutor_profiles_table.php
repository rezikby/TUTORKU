<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000006_add_fields_to_tutor_profiles_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan tutor (instruksi) butuh dokumen yang lebih lengkap & spesifik
     * daripada yang tersedia sekarang (cuma 1 kolom identity_document_path):
     * - Foto Profil (terpisah dari avatar user umum, supaya bisa direview admin)
     * - Foto KTP & Selfie dengan KTP (dua file berbeda)
     * - CV PDF & Sertifikat PDF sudah ada tempatnya (cv_path, tutor_certificates),
     *   tinggal foto KTP/selfie yang ditambahkan di sini.
     * - Provinsi (sebelumnya cuma city)
     * - Like / Dislike untuk halaman detail tutor (5 tombol di bawah video)
     */
    public function up(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->string('province')->nullable()->after('city');
            $table->string('profile_photo_path')->nullable()->after('province');
            $table->string('ktp_photo_path')->nullable()->after('identity_document_path');
            $table->string('selfie_ktp_path')->nullable()->after('ktp_photo_path');
            $table->string('intro_video_path')->nullable()->after('intro_video_url'); // Upload file video, alternatif dari intro_video_url
            $table->unsignedInteger('like_count')->default(0)->after('rating_count');
            $table->unsignedInteger('dislike_count')->default(0)->after('like_count');
            $table->unsignedInteger('view_count')->default(0)->after('dislike_count');
        });
    }

    public function down(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'province', 'profile_photo_path', 'ktp_photo_path', 'selfie_ktp_path',
                'intro_video_path', 'like_count', 'dislike_count', 'view_count',
            ]);
        });
    }
};
