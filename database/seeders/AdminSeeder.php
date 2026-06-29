<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat akun admin default untuk login email+password (tanpa OTP).
 * Ganti email & password di bawah ini sebelum dipakai di production,
 * lalu jalankan: php artisan db:seed --class=AdminSeeder
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'rezikobay75@gmail.com';
        $password = '230107Rezi';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin TUTORKU',
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        if (! $user->settings) {
            UserSetting::create(['user_id' => $user->id]);
        }

        $this->command->info("Admin dibuat: {$email} / password: {$password}");
    }
}
