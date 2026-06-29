<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\SessionReminderNotification;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendSessionReminders extends Command
{
    protected $signature = 'reminders:send {--minutes=15}';
    protected $description = 'Kirim reminder notifikasi untuk session yang akan dimulai';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $this->info("Mencari booking yang akan dimulai dalam {$minutes} menit...");

        // Hitung waktu target: sekarang + X menit
        $now = Carbon::now();
        $targetTime = $now->copy()->addMinutes($minutes);

        // Cari booking yang:
        // 1. Status = 'confirmed'
        // 2. Belum pernah dikirim reminder
        // 3. Waktu sesi berada di rentang [now, now + X menit]
        // 4. User memilih reminder_time sesuai target
        $bookings = Booking::where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->with(['student.settings', 'tutorProfile.user.settings', 'subject'])
            ->get();

        $bookings = $bookings->filter(function (Booking $booking) use ($now, $targetTime, $minutes): bool {
            if (!$booking->date || !$booking->start_time) {
                return false;
            }

            $bookingStart = Carbon::parse($booking->date->format('Y-m-d') . ' ' . $booking->start_time, 'Asia/Jakarta');
            if (!$bookingStart->between($now, $targetTime, true)) {
                return false;
            }

            $studentSettings = $booking->student?->settings;
            if ($studentSettings) {
                return (int) $studentSettings->reminder_time === $minutes;
            }

            return $minutes === 15;
        })->values();

        if ($bookings->isEmpty()) {
            $this->info('Tidak ada booking yang perlu reminder.');
            return 0;
        }

        $this->info("Ditemukan {$bookings->count()} booking.");

        $pushService = app(PushNotificationService::class);
        $sentCount = 0;

        foreach ($bookings as $booking) {
            try {
                $student = $booking->student;
                $tutor = $booking->tutorProfile?->user;

                if (!$student || !$tutor) {
                    Log::warning("Booking {$booking->id} student/tutor tidak ditemukan");
                    continue;
                }

                $userSettings = $student->settings;

                // Kirim notifikasi ke siswa sesuai preferensi
                Notification::sendNow($student, new SessionReminderNotification($booking));

                // Catat channel yang digunakan
                $reminderFlags = [
                    'reminder_sent_email' => $userSettings?->notif_email ?? true,
                    'reminder_sent_whatsapp' => $userSettings?->notif_whatsapp ?? false,
                ];

                // Kirim push notification jika user punya FCM tokens
                if ($userSettings?->notif_push ?? true) {
                    $sent = $pushService->sendToUser(
                        $student,
                        'Sesi Segera Dimulai',
                        "Booking #{$booking->code} akan dimulai pukul " . substr($booking->start_time, 0, 5),
                        [
                            'booking_id' => (string) $booking->id,
                            'type' => 'session_reminder',
                            'action_url' => '/live-class?booking_id=' . $booking->id,
                        ]
                    );
                    $reminderFlags['reminder_sent_push'] = $sent;
                }

                // Kirim reminder ke tutor juga
                $tutorSettings = $tutor->settings;
                Notification::sendNow($tutor, new SessionReminderNotification($booking));

                if ($tutorSettings?->notif_push ?? true) {
                    $pushService->sendToUser(
                        $tutor,
                        'Sesi Segera Dimulai',
                        "Booking #{$booking->code} akan dimulai pukul " . substr($booking->start_time, 0, 5),
                        [
                            'booking_id' => (string) $booking->id,
                            'type' => 'session_reminder',
                            'action_url' => '/live-class?booking_id=' . $booking->id,
                        ]
                    );
                }

                // Update booking dengan timestamp & flags
                $booking->update(array_merge($reminderFlags, [
                    'reminder_sent_at' => $now,
                ]));

                $this->info("✓ Reminder terkirim untuk booking #{$booking->code}");
                $sentCount++;
            } catch (\Exception $e) {
                Log::error("Gagal mengirim reminder booking {$booking->id}: " . $e->getMessage());
                $this->error("✗ Gagal untuk booking #{$booking->code}: {$e->getMessage()}");
            }
        }

        $this->info("Selesai. {$sentCount} reminder terkirim.");
        return 0;
    }
}
