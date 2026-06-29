<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000005_update_payments_table_for_spec.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spesifikasi minta status pembayaran: pending, paid, failed, expired, cancelled
     * (status lama 'refunded' dipetakan ke 'cancelled' karena paling mendekati secara bisnis).
     *
     * Method pembayaran dirinci sesuai daftar di instruksi: qris, ovo, dana, gopay,
     * shopeepay, virtual_account, bank_transfer, cod. Method lama 'ewallet' generik
     * dipetakan ke 'gopay' sebagai default supaya data lama tidak hilang.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method_new')->nullable()->after('method');
            $table->string('status_new')->default('pending')->after('status');
        });

        DB::table('payments')->update([
            'method_new' => DB::raw("CASE method WHEN 'ewallet' THEN 'gopay' ELSE method END"),
            'status_new' => DB::raw("CASE status WHEN 'refunded' THEN 'cancelled' ELSE status END"),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['method', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('method', ['qris', 'ovo', 'dana', 'gopay', 'shopeepay', 'virtual_account', 'bank_transfer', 'cod'])
                ->nullable()->after('gateway');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])
                ->default('pending')->index()->after('amount');
        });

        DB::table('payments')->update([
            'method' => DB::raw('method_new'),
            'status' => DB::raw('status_new'),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['method_new', 'status_new']);
        });
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis presisi untuk perubahan enum bertingkat ini.
    }
};
