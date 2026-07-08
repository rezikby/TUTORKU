<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {


        $subjectsByLevel = [

            // ============================
            // SD / MI (Fase A-C, Kelas 1-6)
            // ============================
            'SD' => [
                'Pendidikan Agama dan Budi Pekerti',
                'Pendidikan Pancasila',
                'Bahasa Indonesia SD',
                'Matematika SD',
                'IPAS SD', // Ilmu Pengetahuan Alam dan Sosial (gabungan IPA+IPS di Kurikulum Merdeka SD)
                'Bahasa Inggris SD', // muatan lokal/mapel tambahan
                'PJOK SD', // Pendidikan Jasmani, Olahraga, dan Kesehatan
                'Seni Musik SD',
                'Seni Rupa SD',
                'Seni Tari SD',
                'Seni Teater SD',
                'Muatan Lokal SD',
            ],

            // ============================
            // SMP / MTs (Fase D, Kelas 7-9)
            // ============================
            'SMP/MTS' => [
                'Pendidikan Agama dan Budi Pekerti SMP',
                'Pendidikan Pancasila SMP',
                'Bahasa Indonesia SMP',
                'Matematika SMP',
                'IPA SMP',
                'IPS SMP',
                'Bahasa Inggris SMP',
                'PJOK SMP',
                'Informatika SMP',
                'Seni Musik SMP',
                'Seni Rupa SMP',
                'Seni Tari SMP',
                'Seni Teater SMP',
                'Prakarya SMP',
                'Muatan Lokal SMP',
            ],

            // ============================
            // SMA / SMK / MA (Fase E-F, Kelas 10-12)
            // ============================
            'SMA/SMK' => [
                // --- Kelompok Mata Pelajaran Umum (wajib semua siswa) ---
                'Pendidikan Agama dan Budi Pekerti SMA',
                'Pendidikan Pancasila SMA',
                'Bahasa Indonesia SMA',
                'Matematika SMA',
                'Bahasa Inggris SMA',
                'PJOK SMA',
                'Sejarah',
                'Seni Budaya SMA',
                'Informatika SMA',
                'Muatan Lokal SMA',

                // --- Kelompok Mata Pelajaran Pilihan (peminatan, tidak ada jurusan kaku IPA/IPS) ---
                'Fisika',
                'Kimia',
                'Biologi',
                'Ekonomi',
                'Sosiologi',
                'Geografi',
                'Antropologi',
                'Bahasa dan Sastra Indonesia Lanjutan',
                'Bahasa Inggris Lanjutan',
                'Matematika Tingkat Lanjut',
                'Prakarya dan Kewirausahaan',

                // --- Kelompok SMK: Mata Pelajaran Kejuruan ---
                'Dasar-Dasar Kejuruan SMK',
                'Konsentrasi Keahlian SMK',
                'Projek Kreatif dan Kewirausahaan SMK',
                'Praktik Kerja Lapangan SMK',

                // --- Persiapan Ujian Masuk PT (di luar struktur resmi Kurikulum Merdeka, tapi umum di bimbel) ---
                'TPS UTBK',
                'Penalaran Matematika UTBK',
                'Literasi Bahasa Indonesia UTBK',
                'Literasi Bahasa Inggris UTBK',
            ],

            // ============================
            // Universitas / Politeknik
            // (tidak diatur Kurikulum Merdeka K-12, disusun umum lintas jurusan)
            // ============================
            'Universitas/Politeknik' => [
                'Matematika Dasar',
                'Kalkulus',
                'Aljabar Linear',
                'Statistika',
                'Statistika Probabilitas',
                'Fisika Dasar',
                'Kimia Dasar',
                'Pemrograman',
                'Algoritma',
                'Struktur Data',
                'Basis Data',
                'Jaringan Komputer',
                'Sistem Operasi',
                'Rekayasa Perangkat Lunak',
                'Kecerdasan Buatan',
                'Ekonomi Mikro',
                'Ekonomi Makro',
                'Akuntansi Dasar',
                'Manajemen',
                'Bahasa Inggris Akademik',
                'Metodologi Penelitian',
                'Coding & Komputer',
            ],

        ];

        $subjects = [];
        foreach ($subjectsByLevel as $levelSubjects) {
            $subjects = array_merge($subjects, $levelSubjects);
        }

        foreach ($subjects as $name) {
            Subject::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
