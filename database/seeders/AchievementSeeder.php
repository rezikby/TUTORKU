<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            ['code' => 'first_session', 'name' => 'Sesi Pertama', 'description' => 'Menyelesaikan sesi belajar pertama.', 'icon' => 'sparkles'],
            ['code' => 'streak_7', 'name' => 'Konsisten 7 Hari', 'description' => 'Belajar 7 hari berturut-turut.', 'icon' => 'flame'],
            ['code' => 'sessions_10', 'name' => '10 Sesi Selesai', 'description' => 'Menyelesaikan 10 sesi belajar.', 'icon' => 'trophy'],
            ['code' => 'forum_helper', 'name' => 'Penolong Forum', 'description' => 'Jawaban ditandai sebagai solusi di forum.', 'icon' => 'award'],
            ['code' => 'high_scorer', 'name' => 'Nilai Tertinggi', 'description' => 'Mendapat nilai evaluasi di atas 90.', 'icon' => 'target'],
        ];

        foreach ($achievements as $achievement) {
            Achievement::firstOrCreate(['code' => $achievement['code']], $achievement);
        }
    }
}
