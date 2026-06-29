<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\TeamMember;
use Illuminate\Database\Seeder;

class AboutSeeder extends Seeder
{
    public function run(): void
    {
        $team = [
            ['name' => 'Rezi', 'role' => 'Founder & CEO', 'order' => 1, 'bio' => 'Memimpin pengembangan dan strategi produk TUTORKU.'],
            ['name' => 'Mita Yuliana', 'role' => 'Co-Founder & COO', 'order' => 2, 'bio' => 'Mengelola operasi tutor dan kualitas layanan.'],
            ['name' => 'Alif Pramulia Nugraha', 'role' => 'Head of Engineering', 'order' => 3, 'bio' => 'Bertanggung jawab atas arsitektur teknis dan pengembangan platform.'],
        ];

        foreach ($team as $member) {
            TeamMember::firstOrCreate(['name' => $member['name']], $member);
        }

        $faqs = [
            ['question' => 'Bagaimana cara memesan sesi dengan tutor?', 'answer' => 'Cari tutor sesuai kebutuhanmu, pilih jadwal yang tersedia, lalu selesaikan pembayaran untuk mengonfirmasi booking.', 'order' => 1],
            ['question' => 'Apakah saya bisa membatalkan booking?', 'answer' => 'Bisa, selama sesi belum dimulai kamu dapat membatalkan booking melalui halaman Dashboard.', 'order' => 2],
            ['question' => 'Bagaimana cara menjadi tutor di TUTORKU?', 'answer' => 'Daftar sebagai tutor, lengkapi data diri, pendidikan, dan sertifikat, lalu ajukan verifikasi ke admin.', 'order' => 3],
            ['question' => 'Metode pembayaran apa saja yang didukung?', 'answer' => 'TUTORKU mendukung QRIS, e-wallet, dan transfer bank/virtual account melalui Midtrans atau Xendit.', 'order' => 4],
        ];

        foreach ($faqs as $faq) {
            Faq::firstOrCreate(['question' => $faq['question']], $faq);
        }
    }
}
