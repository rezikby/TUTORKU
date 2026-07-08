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
            ['name' => 'Mita Yuliana', 'email' => 'tutorku1@gmail.com', 'subjects' => [], 'level' => 'SD', 'mode' => 'both'],
            ['name' => 'Rezi', 'email' => 'tutorku2@gmail.com', 'subjects' => [], 'level' => 'SD', 'mode' => 'online'],
            ['name' => 'Andre Kusuma', 'email' => 'tutorku3@gmail.com', 'subjects' => [], 'level' => 'SD', 'mode' => 'offline'],
            ['name' => 'Nesa Ari', 'email' => 'tutorku4@gmail.com', 'subjects' => [], 'level' => 'SMP/MTS', 'mode' => 'both'],
            ['name' => 'Nurdhidayah', 'email' => 'tutorku5@gmail.com', 'subjects' => [], 'level' => 'SMP/MTS', 'mode' => 'offline'],
            ['name' => 'Alif Pramulia Nugraha', 'email' => 'tutorku6@gmail.com', 'subjects' => [], 'level' => 'SMP/MTS', 'mode' => 'online'],
            ['name' => 'Dimas Saputra', 'email' => 'tutorku7@gmail.com', 'subjects' => [], 'level' => 'SMA/SMK', 'mode' => 'both'],
            ['name' => 'Siti Rahma', 'email' => 'tutorku8@gmail.com', 'subjects' => [], 'level' => 'SMA/SMK', 'mode' => 'offline'],
            ['name' => 'Ahmad Fauzan', 'email' => 'tutorku9@gmail.com', 'subjects' => [], 'level' => 'SMA/SMK', 'mode' => 'online'],
            ['name' => 'Rina Oktavia', 'email' => 'tutorku10@gmail.com', 'subjects' => [], 'level' => 'Universitas/Politeknik', 'mode' => 'both'],
            ['name' => 'Yoga Pratama', 'email' => 'tutorku11@gmail.com', 'subjects' => [], 'level' => 'Universitas/Politeknik', 'mode' => 'offline'],
            ['name' => 'Putri Anggraini', 'email' => 'tutorku12@gmail.com', 'subjects' => [], 'level' => 'Universitas/Politeknik', 'mode' => 'online'],
        ];

        foreach ($data as $row) {
            $user = $this->createUser($row['name'], $row['email'], 'tutor');

            $modeOnline = $row['mode'] !== 'offline';
            $modeOffline = $row['mode'] !== 'online';

            $profile = TutorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => implode(' & ', $row['subjects']),
                    'bio' => 'Tutor berpengalaman siap membantu kamu memahami materi dengan cara yang menyenangkan.',
                    'price_per_hour' => 75000,
                    'experience_years' => 3,
                    'levels' => [$row['level']],
                    'mode_online' => $modeOnline,
                    'mode_offline' => $modeOffline,
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
                'major' => $row['subjects'][0] ?? null,
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