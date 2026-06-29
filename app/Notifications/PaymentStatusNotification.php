<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paid = $this->payment->isSuccessful();

        return (new MailMessage)
            ->subject($paid ? 'Pembayaran Berhasil' : 'Status Pembayaran: '.ucfirst($this->payment->status))
            ->line('Pembayaran #'.$this->payment->invoice_number.' sebesar Rp'.number_format($this->payment->amount, 0, ',', '.').' '.($paid ? 'telah berhasil diterima.' : 'berstatus '.$this->payment->status.'.'))
            ->action('Lihat Booking', config('app.frontend_url').'/booking/'.$this->payment->booking_id);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $paid = $this->payment->isSuccessful();

        return $paid
            ? "Pembayaran #{$this->payment->invoice_number} sebesar Rp".number_format($this->payment->amount, 0, ',', '.').' berhasil diterima. Booking kamu sudah dikonfirmasi.'
            : "Status pembayaran #{$this->payment->invoice_number}: {$this->payment->status}.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'payment',
            'title' => $this->payment->isSuccessful() ? 'Pembayaran Berhasil' : 'Status Pembayaran',
            'message' => 'Invoice #'.$this->payment->invoice_number.' - Rp'.number_format($this->payment->amount, 0, ',', '.'),
            'action_url' => '/booking/'.$this->payment->booking_id,
            'payment_id' => $this->payment->id,
        ];
    }
}
