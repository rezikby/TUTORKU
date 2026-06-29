<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000003_create_login_activities_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log setiap aktivitas login (Google OAuth maupun Phone OTP) beserta
     * informasi device/IP untuk keperluan "Device Tracking" & "Session Management"
     * yang diminta. Dikaitkan ke personal_access_tokens (Sanctum) lewat token_id
     * supaya saat user logout dari satu device, kita tahu device mana yang dicabut.
     */
    public function up(): void
    {
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->enum('method', ['google', 'phone_otp', 'password'])->default('google');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};
