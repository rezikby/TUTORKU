<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SessionStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'session',
            'title' => 'Sesi dimulai',
            'message' => 'Tutor telah memulai sesi belajar kamu. Masuk ke ruang kelas sekarang.',
            'action_url' => '/live-class?booking_id=' . $this->booking->id,
            'booking_id' => $this->booking->id,
        ];
    }
}
