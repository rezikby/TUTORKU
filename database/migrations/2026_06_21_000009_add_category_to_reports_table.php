<?php
/**
 * FILE: backend/database/migrations/2026_06_21_000009_add_category_to_reports_table.php
 * STATUS: BARU
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instruksi REPORT TUTOR butuh kategori baku (Penipuan, Spam, Konten Tidak Sesuai,
     * Pelecehan, Lainnya) — sebelumnya reports hanya punya kolom 'reason' bebas teks.
     * Kolom 'category' ditambahkan, 'reason' tetap ada untuk keterangan tambahan/"Lainnya".
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('category', ['penipuan', 'spam', 'konten_tidak_sesuai', 'pelecehan', 'lainnya'])
                ->default('lainnya')->after('reportable_id');
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
