<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Kirim reminder user berdasarkan konfigurasi mereka
        $schedule->command('reminders:send', ['--minutes=15'])
            ->everyMinute()
            ->name('send-session-reminders-15min')
            ->withoutOverlapping();

        $schedule->command('reminders:send', ['--minutes=30'])
            ->everyMinute()
            ->name('send-session-reminders-30min')
            ->withoutOverlapping();

        $schedule->command('reminders:send', ['--minutes=60'])
            ->everyMinute()
            ->name('send-session-reminders-60min')
            ->withoutOverlapping();

        $schedule->command('bookings:expire-pending')
            ->everyFiveMinutes()
            ->name('expire-pending-bookings')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
