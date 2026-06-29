<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000002_create_otp_codes_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan kode OTP untuk dua flow:
     * - 'google_email'  : OTP 5 digit dikirim ke email Google setelah OAuth sukses.
     * - 'phone'         : OTP dikirim via WhatsApp (Fonnte) ke nomor telepon.
     *
     * 'identifier' = email atau nomor telepon yang dituju.
     * Satu baris per permintaan OTP (history disimpan, tidak di-update di tempat)
     * supaya rate limiting & audit log lebih mudah dihitung dari tabel ini saja.
     */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index(); // email atau nomor telepon
            $table->enum('purpose', ['google_email', 'phone'])->index();
            $table->string('code', 5);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
