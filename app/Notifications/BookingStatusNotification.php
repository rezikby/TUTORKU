<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking, public string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    protected function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'dikonfirmasi',
            'cancelled' => 'dibatalkan',
            'completed' => 'selesai',
            default => $this->status,
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking #'.$this->booking->code.' telah '.$this->statusLabel())
            ->line('Booking sesi belajar kamu pada tanggal '.$this->booking->date->translatedFormat('d M Y').' telah '.$this->statusLabel().'.')
            ->action('Lihat Detail Booking', config('app.frontend_url').'/booking/'.$this->booking->id);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return "Halo {$notifiable->name}, booking #{$this->booking->code} kamu telah {$this->statusLabel()}. Cek detail di aplikasi TUTORKU.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'booking',
            'title' => 'Booking '.ucfirst($this->statusLabel()),
            'message' => 'Booking #'.$this->booking->code.' telah '.$this->statusLabel().'.',
            'action_url' => '/booking/'.$this->booking->id,
            'booking_id' => $this->booking->id,
        ];
    }
}
