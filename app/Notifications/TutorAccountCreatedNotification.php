<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim sekali saat pengajuan tutor disetujui admin, berisi password
 * yang baru di-generate untuk login ke Dashboard Tutor (email+password,
 * tanpa OTP, beda dengan login siswa). Boleh di-queue karena bukan
 * kebutuhan real-time seperti OTP -- keterlambatan beberapa menit aman.
 */
class TutorAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $password)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return (new MailMessage)
            ->subject('Akun Tutor TUTORKU Kamu Sudah Aktif')
            ->greeting("Selamat, {$notifiable->name}!")
            ->line('Pengajuan tutor kamu telah disetujui. Akun tutor kamu sudah aktif.')
            ->line('Gunakan kredensial berikut untuk masuk ke Dashboard Tutor:')
            ->line("Email: {$notifiable->email}")
            ->line("Password: {$this->password}")
            ->action('Masuk ke Dashboard Tutor', "{$frontendUrl}/?v=".time()."#/tutor-login")
            ->line('Demi keamanan, segera ganti password kamu setelah login lewat menu Profil.')
            ->line('Kamu juga bisa masuk memakai akun Google yang sama dengan email ini.');
    }
}
