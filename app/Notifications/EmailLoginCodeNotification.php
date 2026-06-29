<?php

namespace App\Notifications;

use App\Models\LoginCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailLoginCodeNotification extends Notification
{
    use Queueable;

    public function __construct(protected LoginCode $loginCode)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi TUTORKU')
            ->line('Masukkan kode 5 digit berikut untuk menyelesaikan login atau pendaftaran di TUTORKU:')
            ->line('Kode: '.$this->loginCode->code)
            ->line('Kode ini berlaku sampai '.optional($this->loginCode->expires_at)->format('H:i').' WIB.')
            ->line('Jika kamu tidak meminta kode ini, abaikan saja pesan ini.');
    }
}
