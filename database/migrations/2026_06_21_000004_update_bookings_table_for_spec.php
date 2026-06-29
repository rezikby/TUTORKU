<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000004_update_bookings_table_for_spec.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * - Tambah field lokasi belajar offline lengkap: kota, provinsi, titik lokasi
     *   (latitude/longitude Google Maps), sesuai instruksi BOOKING > Jika offline.
     * - Status booking disamakan dengan spesifikasi: pending, confirmed, completed, cancelled.
     *   Status lama 'pending_payment', 'ongoing', 'expired' dipetakan ke status terdekat
     *   supaya data lama tetap konsisten, tanpa kehilangan informasi pembayaran
     *   (detail pembayaran tetap ada di tabel payments).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('location_city')->nullable()->after('location_address');
            $table->string('location_province')->nullable()->after('location_city');
            $table->decimal('location_latitude', 10, 7)->nullable()->after('location_province');
            $table->decimal('location_longitude', 10, 7)->nullable()->after('location_latitude');
            $table->text('location_note')->nullable()->after('location_longitude'); // Titik lokasi / patokan belajar
        });

        // Ganti enum status agar sesuai spesifikasi: pending, confirmed, completed, cancelled.
        // SQLite/MySQL enum diubah lewat string sementara supaya data lama tidak hilang.
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status_new')->default('pending')->after('status');
        });

        DB::table('bookings')->update([
            'status_new' => DB::raw("CASE status
                WHEN 'pending_payment' THEN 'pending'
                WHEN 'ongoing' THEN 'confirmed'
                WHEN 'expired' THEN 'cancelled'
                ELSE status END"),
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])
                ->default('pending')->index()->after('total_price');
        });

        DB::table('bookings')->update(['status' => DB::raw('status_new')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('status_new');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'location_city', 'location_province', 'location_latitude', 'location_longitude', 'location_note',
            ]);
        });
    }
};
