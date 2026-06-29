<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        $settings = $notifiable->settings ?? null;

        $channels = ['database', 'broadcast'];

        if (($settings?->notif_email ?? true) && $this->shouldSendEmail()) {
            $channels[] = 'mail';
        }

        if (($settings?->notif_whatsapp ?? true) && config('services.whatsapp.api_url')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    protected function shouldSendEmail(): bool
    {
        return app()->environment('production') || config('mail.default') === 'log';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengingat: Sesi belajar segera dimulai')
            ->line('Sesi belajar kamu dijadwalkan pukul '.substr($this->booking->start_time, 0, 5).' hari ini.')
            ->action('Masuk ke Live Class', config('app.frontend_url').'/live-class/'.$this->booking->id);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Pengingat: Sesi belajar kamu (booking #{$this->booking->code}) akan dimulai pukul ".substr($this->booking->start_time, 0, 5).' hari ini. Jangan sampai terlewat ya!';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'reminder',
            'title' => 'Sesi Segera Dimulai',
            'message' => 'Booking #'.$this->booking->code.' akan dimulai pukul '.substr($this->booking->start_time, 0, 5).'.',
            'action_url' => '/live-class/'.$this->booking->id,
            'booking_id' => $this->booking->id,
        ];
    }
}
