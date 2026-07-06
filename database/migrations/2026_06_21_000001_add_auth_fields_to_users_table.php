<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000001_add_auth_fields_to_users_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom yang dibutuhkan untuk:
     * - Login Google OAuth (google_id, google_avatar)
     * - Login Nomor Telepon OTP (phone_verified_at)
     * - Status verifikasi email (dipakai ulang untuk OTP Google)
     * - Remember login (remember_token sudah ada dari migration users, tidak diubah)
     *
     * Kolom 'password' dibuat nullable karena akun yang dibuat via Google/OTP
     * tidak punya password sama sekali (tidak ada login email+password lagi).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('google_avatar')->nullable()->after('google_id');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->boolean('remember_login')->default(false)->after('phone_verified_at');
        });

        // Password jadi nullable: tidak ada lagi form register email+password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->update(['password' => '']);

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'google_id')) {
                $table->dropColumn('google_id');
            }
            if (Schema::hasColumn('users', 'google_avatar')) {
                $table->dropColumn('google_avatar');
            }
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }
            if (Schema::hasColumn('users', 'remember_login')) {
                $table->dropColumn('remember_login');
            }
            $table->string('password')->nullable(false)->change();
        });
    }
};
