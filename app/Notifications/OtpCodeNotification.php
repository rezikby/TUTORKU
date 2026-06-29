<?php
/**
 * FILE: backend/app/Notifications/OtpCodeNotification.php
 * STATUS: DIUBAH (PENTING: hapus ShouldQueue agar OTP terkirim langsung)
 */


namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * TIDAK menggunakan ShouldQueue secara sengaja: OTP adalah kebutuhan real-time
 * (user menunggu kode di halaman login), jadi harus terkirim langsung secara
 * synchronous. Jika di-queue dan tidak ada `php artisan queue:work` berjalan,
 * OTP tidak akan pernah terkirim sampai worker diaktifkan — user akan stuck.
 */
class OtpCodeNotification extends Notification
{
    public function __construct(
        public string $code,
        public int $expiresMinutes,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode OTP TUTORKU Kamu')
            ->greeting('Halo!')
            ->line('Gunakan kode berikut untuk menyelesaikan proses login ke TUTORKU:')
            ->line(new \Illuminate\Support\HtmlString(
                '<div style="text-align:center;margin:24px 0;">'
                .'<span style="font-size:32px;font-weight:700;letter-spacing:8px;color:#3B7EFF;">'.$this->code.'</span>'
                .'</div>'
            ))
            ->line("Kode ini berlaku selama {$this->expiresMinutes} menit.")
            ->line('Jangan bagikan kode ini ke siapapun, termasuk pihak yang mengaku sebagai admin TUTORKU.')
            ->line('Jika kamu tidak meminta kode ini, abaikan saja email ini.');
    }
}
