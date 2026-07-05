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
        $password = $extra['password'] ?? '230107Rezi';
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
            ['name' => 'Mita Yuliana', 'email' => 'rezicopilot1@gmail.com', 'subjects' => ['Matematika', 'Fisika'], 'city' => 'Jakarta Selatan'],
            ['name' => 'Rezi', 'email' => 'rezicopilot2@gmail.com', 'subjects' => ['Bahasa Inggris'], 'city' => 'Bandung'],
            ['name' => 'Andre Kusuma', 'email' => 'rezicopilot3@gmail.com', 'subjects' => ['Kimia', 'Biologi'], 'city' => 'Surabaya'],
            ['name' => 'Nesa Ari', 'email' => 'rezicopilot4@gmail.com', 'subjects' => ['Matematika'], 'city' => 'Jakarta Pusat'],
            ['name' => 'Nurdhidayah', 'email' => 'rezicopilot5@gmail.com', 'subjects' => ['Fisika', 'Matematika'], 'city' => 'Yogyakarta'],
            ['name' => 'Alif Pramulia Nugraha', 'email' => 'rezicopilot6@gmail.com', 'subjects' => ['Bahasa Indonesia'], 'city' => 'Malang'],
            ['name' => 'Dimas Saputra', 'email' => 'rezicopilot7@gmail.com', 'subjects' => ['Matematika', 'Bahasa Inggris'], 'city' => 'Bekasi'],
            ['name' => 'Siti Rahma', 'email' => 'rezicopilot8@gmail.com', 'subjects' => ['Biologi', 'Kimia'], 'city' => 'Semarang'],
            ['name' => 'Ahmad Fauzan', 'email' => 'rezicopilot9@gmail.com', 'subjects' => ['Fisika'], 'city' => 'Bandung'],
            ['name' => 'Rina Oktavia', 'email' => 'rezicopilot10@gmail.com', 'subjects' => ['Bahasa Inggris', 'Bahasa Indonesia'], 'city' => 'Depok'],
            ['name' => 'Yoga Pratama', 'email' => 'rezicopilot11@gmail.com', 'subjects' => ['Matematika', 'UTBK'], 'city' => 'Tangerang'],
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
