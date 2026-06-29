<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $code;
    protected int $expiresMinutes;

    public function __construct(string $code, int $expiresMinutes)
    {
        $this->code = $code;
        $this->expiresMinutes = $expiresMinutes;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔐 Kode Verifikasi TUTORKU')
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk verifikasi email Anda di TUTORKU.')
            ->line('Gunakan kode berikut untuk menyelesaikan proses:')
            ->line("**Kode OTP: {$this->code}**")
            ->line("Kode ini berlaku selama {$this->expiresMinutes} menit.")
            ->line('Jangan berikan kode ini kepada siapapun.')
            ->salutation('Salam, Tim TUTORKU');
    }
}