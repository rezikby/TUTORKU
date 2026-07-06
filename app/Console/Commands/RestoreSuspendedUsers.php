<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RestoreSuspendedUsers extends Command
{
    protected $signature = 'users:restore-suspended';
    protected $description = 'Restore user yang suspend sementara setelah durasi berakhir';

    public function handle(): int
    {
        $users = User::query()
            ->where('status', 'suspended')
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada pengguna suspend sementara yang perlu direstore.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $user->update([
                'status' => 'active',
                'suspended_until' => null,
            ]);
        }

        $this->info("{$users->count()} pengguna suspend sementara berhasil direstore.");
        return self::SUCCESS;
    }
}
