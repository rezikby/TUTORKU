<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubjectSeeder::class,
            AboutSeeder::class,
        ]);

        $this->seedTutors();
    }

    protected function createUser(string $name, string $email, string $role, array $extra = []): User
    {
        $password = $extra['password'] ?? 'password';
        unset($extra['password']);

        $user = User::updateOrCreate(
            ['email' => $email],
            array_merge([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'email_verified_at' => now(),
                'status' => 'active',
            ], $extra)
        );

        UserSetting::firstOrCreate(['user_id' => $user->id]);

        return $user;
    }

    protected function seedTutors(): void
    {
        $data = [
            ['name' => 'Mita Yuliana', 'email' => 'tutorku1@gmail.com', 'subjects' => ['Matematika', 'Fisika'], 'city' => 'Jakarta Selatan'],
            ['name' => 'Rezi', 'email' => 'tutorku2@gmail.com', 'subjects' => ['Bahasa Inggris'], 'city' => 'Bandung'],
            ['name' => 'Andre Kusuma', 'email' => 'tutorku3@gmail.com', 'subjects' => ['Kimia', 'Biologi'], 'city' => 'Surabaya'],
            ['name' => 'Nesa Ari', 'email' => 'tutorku4@gmail.com', 'subjects' => ['Matematika'], 'city' => 'Jakarta Pusat'],
            ['name' => 'Nurdhidayah', 'email' => 'tutorku5@gmail.com', 'subjects' => ['Fisika', 'Matematika'], 'city' => 'Yogyakarta'],
            ['name' => 'Alif Pramulia Nugraha', 'email' => 'tutorku6@gmail.com', 'subjects' => ['Bahasa Indonesia'], 'city' => 'Malang'],
            ['name' => 'Dimas Saputra', 'email' => 'tutorku7@gmail.com', 'subjects' => ['Matematika', 'Bahasa Inggris'], 'city' => 'Bekasi'],
            ['name' => 'Siti Rahma', 'email' => 'tutorku8@gmail.com', 'subjects' => ['Biologi', 'Kimia'], 'city' => 'Semarang'],
            ['name' => 'Ahmad Fauzan', 'email' => 'tutorku9@gmail.com', 'subjects' => ['Fisika'], 'city' => 'Bandung'],
            ['name' => 'Rina Oktavia', 'email' => 'tutorku10@gmail.com', 'subjects' => ['Bahasa Inggris', 'Bahasa Indonesia'], 'city' => 'Depok'],
            ['name' => 'Yoga Pratama', 'email' => 'tutorku11@gmail.com', 'subjects' => ['Matematika', 'UTBK'], 'city' => 'Tangerang'],
            ['name' => 'Putri Anggraini', 'email' => 'tutorku12@gmail.com', 'subjects' => ['Bahasa Inggris'], 'city' => 'Jakarta Barat'],
            ['name' => 'Bagus Setiawan', 'email' => 'tutorku13@gmail.com', 'subjects' => ['Fisika', 'UTBK'], 'city' => 'Surabaya'],
            ['name' => 'Dewi Lestari', 'email' => 'tutorku14@gmail.com', 'subjects' => ['Biologi'], 'city' => 'Yogyakarta'],
            ['name' => 'Fajar Nugroho', 'email' => 'tutorku15@gmail.com', 'subjects' => ['Kimia', 'Fisika'], 'city' => 'Medan'],
            ['name' => 'Intan Permatasari', 'email' => 'tutorku16@gmail.com', 'subjects' => ['Matematika', 'Kimia'], 'city' => 'Bandung'],
            ['name' => 'Joko Widianto', 'email' => 'tutorku17@gmail.com', 'subjects' => ['Bahasa Indonesia', 'UTBK'], 'city' => 'Semarang'],
            ['name' => 'Kartika Sari', 'email' => 'tutorku18@gmail.com', 'subjects' => ['Bahasa Inggris', 'Bahasa Indonesia'], 'city' => 'Jakarta Timur'],
            ['name' => 'Luthfi Hakim', 'email' => 'tutorku19@gmail.com', 'subjects' => ['Matematika', 'Fisika'], 'city' => 'Depok'],
            ['name' => 'Melati Wulandari', 'email' => 'tutorku20@gmail.com', 'subjects' => ['Biologi', 'Kimia'], 'city' => 'Malang'],
            ['name' => 'Nanda Prasetyo', 'email' => 'tutorku21@gmail.com', 'subjects' => ['UTBK', 'Matematika'], 'city' => 'Bekasi'],
            ['name' => 'Olivia Rahayu', 'email' => 'tutorku22@gmail.com', 'subjects' => ['Bahasa Inggris'], 'city' => 'Tangerang'],
            ['name' => 'Panji Anggoro', 'email' => 'tutorku23@gmail.com', 'subjects' => ['Fisika', 'Matematika'], 'city' => 'Jakarta Selatan'],
            ['name' => 'Qonita Amalia', 'email' => 'tutorku24@gmail.com', 'subjects' => ['Kimia'], 'city' => 'Yogyakarta'],
            ['name' => 'Rangga Saputra', 'email' => 'tutorku25@gmail.com', 'subjects' => ['Bahasa Indonesia'], 'city' => 'Surabaya'],
            ['name' => 'Salsabila Putri', 'email' => 'tutorku26@gmail.com', 'subjects' => ['Biologi', 'UTBK'], 'city' => 'Bandung'],
            ['name' => 'Taufik Hidayat', 'email' => 'tutorku27@gmail.com', 'subjects' => ['Matematika', 'Bahasa Inggris'], 'city' => 'Medan'],
            ['name' => 'Uswatun Khasanah', 'email' => 'tutorku28@gmail.com', 'subjects' => ['Bahasa Indonesia', 'Bahasa Inggris'], 'city' => 'Semarang'],
            ['name' => 'Vino Ardiansyah', 'email' => 'tutorku29@gmail.com', 'subjects' => ['Fisika', 'Kimia'], 'city' => 'Jakarta Pusat'],
            ['name' => 'Widya Ningsih', 'email' => 'tutorku30@gmail.com', 'subjects' => ['Matematika', 'UTBK'], 'city' => 'Depok'],
        ];

        foreach ($data as $row) {
            $user = $this->createUser($row['name'], $row['email'], 'tutor', ['city' => $row['city']]);

            $profile = TutorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => implode(' & ', $row['subjects']),
                    'bio' => 'Tutor berpengalaman siap membantu kamu memahami materi dengan cara yang menyenangkan.',
                    'price_per_hour' => 75000,
                    'experience_years' => 3,
                    'city' => $row['city'],
                    'levels' => ['SMA', 'Mahasiswa'],
                    'mode_online' => true,
                    'mode_offline' => true,
                    'badge' => 'Verified',
                    'verification_status' => 'verified',
                    'registration_step' => 5,
                    'registration_submitted' => true,
                    'total_students' => 24,
                    'total_sessions' => 48,
                ]
            );

            $subjectIds = Subject::whereIn('name', $row['subjects'])->pluck('id');
            $profile->subjects()->sync($subjectIds);

            $profile->educations()->firstOrCreate([
                'degree' => 'S1',
                'institution' => 'Universitas Indonesia',
                'major' => $row['subjects'][0],
            ], ['year_start' => 2015, 'year_end' => 2019]);

            $profile->availabilities()->firstOrCreate([
                'day_of_week' => 1,
                'start_time' => '15:00',
                'end_time' => '20:00',
            ]);
            $profile->availabilities()->firstOrCreate([
                'day_of_week' => 3,
                'start_time' => '15:00',
                'end_time' => '20:00',
            ]);
            $profile->availabilities()->firstOrCreate([
                'day_of_week' => 6,
                'start_time' => '09:00',
                'end_time' => '15:00',
            ]);
        }
    }
}