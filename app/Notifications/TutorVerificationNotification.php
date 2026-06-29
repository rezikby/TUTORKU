<?php

namespace App\Notifications;

use App\Models\TutorProfile;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TutorVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TutorProfile $tutorProfile)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail', WhatsAppChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verified = $this->tutorProfile->verification_status === 'verified';

        return (new MailMessage)
            ->subject($verified ? 'Selamat! Akun tutor kamu telah diverifikasi' : 'Verifikasi akun tutor ditolak')
            ->line($verified
                ? 'Selamat, profil tutor kamu sudah diverifikasi oleh admin TUTORKU dan kini tampil di pencarian.'
                : 'Mohon maaf, pengajuan verifikasi tutor kamu belum bisa disetujui. Alasan: '.$this->tutorProfile->verification_note);
    }

    public function toWhatsApp(object $notifiable): string
    {
        $verified = $this->tutorProfile->verification_status === 'verified';

        return $verified
            ? 'Selamat! Akun tutor TUTORKU kamu sudah diverifikasi dan siap menerima booking.'
            : 'Pengajuan verifikasi tutor kamu ditolak. Alasan: '.$this->tutorProfile->verification_note;
    }

    public function toArray(object $notifiable): array
    {
        $verified = $this->tutorProfile->verification_status === 'verified';

        return [
            'category' => 'system',
            'title' => $verified ? 'Akun Terverifikasi' : 'Verifikasi Ditolak',
            'message' => $verified ? 'Profil tutor kamu sudah diverifikasi.' : $this->tutorProfile->verification_note,
            'action_url' => '/dashboard-tutor',
        ];
    }
}
