<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Channel notifikasi WhatsApp via Fonnte (https://fonnte.com).
 * Fonnte dipilih karena menyediakan free trial & harga terjangkau untuk MVP,
 * cocok dipakai sebelum naik ke provider resmi WhatsApp Business API.
 *
 * Notification class yang ingin kirim WhatsApp cukup mengimplementasikan
 * method toWhatsApp($notifiable): string
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $phone = $notifiable->routeNotificationFor('whatsapp') ?? $notifiable->phone ?? null;
        $token = config('services.whatsapp.token');

        if (! $phone || ! $token) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        $response = Http::asForm()
            ->withHeaders(['Authorization' => $token])
            ->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
            ]);

        if ($response->failed()) {
            Log::warning('Gagal mengirim notifikasi WhatsApp', [
                'phone' => $phone,
                'response' => $response->body(),
            ]);
        }
    }
}
