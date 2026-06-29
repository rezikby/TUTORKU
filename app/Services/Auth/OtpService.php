<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Notifications\OtpCodeNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    protected int $length;
    protected int $expiresMinutes;
    protected int $maxAttempts;
    protected int $resendSeconds;

    public function __construct()
    {
        $this->length = (int) config('services.otp.length', 5);
        $this->expiresMinutes = (int) config('services.otp.expires_minutes', 5);
        $this->maxAttempts = (int) config('services.otp.max_attempts', 5);
        $this->resendSeconds = (int) config('services.otp.resend_seconds', 60);
    }

    public function send(string $identifier, string $purpose, ?string $ipAddress = null): OtpCode
    {
        try {
            if ($purpose === 'phone') {
                $identifier = $this->formatPhoneNumber($identifier);
            }

            $lastSent = OtpCode::where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->latest('created_at')
                ->first();

            if ($lastSent && $lastSent->created_at->diffInSeconds(now()) < $this->resendSeconds) {
                $wait = $this->resendSeconds - $lastSent->created_at->diffInSeconds(now());
                throw new \RuntimeException("Mohon tunggu {$wait} detik sebelum meminta OTP baru.");
            }

            $min = (int) ('1' . str_repeat('0', $this->length - 1));
            $max = (int) str_repeat('9', $this->length);
            $code = (string) random_int($min, $max);

            $otp = OtpCode::create([
                'identifier' => $identifier,
                'purpose' => $purpose,
                'code' => $code,
                'attempts' => 0,
                'expires_at' => now()->addMinutes($this->expiresMinutes),
                'ip_address' => $ipAddress,
            ]);

            $this->dispatch($identifier, $purpose, $code);

            return $otp;
        } catch (\Exception $e) {
            Log::error('OTP send error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verify(string $identifier, string $purpose, string $code): array
    {
        try {
            if ($purpose === 'phone') {
                $identifier = $this->formatPhoneNumber($identifier);
            }

            $otp = OtpCode::where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->latest('created_at')
                ->first();

            if (! $otp) {
                return ['success' => false, 'message' => 'Kode OTP tidak ditemukan. Silakan minta kode baru.'];
            }

            if ($otp->isExpired()) {
                return ['success' => false, 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.'];
            }

            if ($otp->maxAttemptsReached()) {
                return ['success' => false, 'message' => 'Kamu sudah mencapai batas maksimal percobaan. Silakan minta kode baru.'];
            }

            if (! hash_equals($otp->code, $code)) {
                $otp->increment('attempts');
                $sisa = $this->maxAttempts - $otp->attempts;
                return [
                    'success' => false,
                    'message' => $sisa > 0
                        ? "Kode OTP salah. Sisa percobaan: {$sisa}."
                        : 'Kode OTP salah. Kamu sudah mencapai batas maksimal percobaan.',
                ];
            }

            $otp->update(['verified_at' => now()]);
            return ['success' => true, 'message' => 'Verifikasi berhasil.'];
        } catch (\Exception $e) {
            Log::error('OTP verify error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan saat verifikasi.'];
        }
    }

    protected function dispatch(string $identifier, string $purpose, string $code): void
    {
        if ($purpose === 'google_email') {
            $this->sendEmail($identifier, $code);
            return;
        }
        $this->sendWhatsapp($identifier, $code);
    }

    protected function sendEmail(string $email, string $code): void
    {
        try {
            (new AnonymousNotifiable)
                ->route('mail', $email)
                ->notify(new OtpCodeNotification($code, $this->expiresMinutes));
        } catch (\Exception $e) {
            Log::error('Send email OTP error: ' . $e->getMessage());
        }
    }

    protected function sendWhatsapp(string $phone, string $code): void
    {
        try {
            $token = config('services.whatsapp.token');
            $phone = $this->formatPhoneNumber($phone);

            $message = "🔐 *Kode Verifikasi TUTORKU*\n\n";
            $message .= "Kode OTP Anda: *{$code}*\n\n";
            $message .= "Kode ini berlaku selama {$this->expiresMinutes} menit.\n";
            $message .= "Jangan berikan kode ini kepada siapapun.\n\n";
            $message .= "© TUTORKU - Belajar. Tumbuh. Berprestasi.";

            if (! $token) {
                Log::info("[OTP DEV-MODE] WhatsApp OTP ke {$phone}: {$code}");
                return;
            }

            $response = Http::withHeaders([
                'Authorization' => $token
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
            ]);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status']) {
                Log::info('WhatsApp OTP berhasil dikirim', [
                    'phone' => $phone,
                    'status' => $result['status']
                ]);
            } else {
                Log::error('WhatsApp OTP gagal dikirim', [
                    'phone' => $phone,
                    'response' => $result
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp OTP error: ' . $e->getMessage(), [
                'phone' => $phone
            ]);
        }
    }

    protected function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }
        if (substr($phone, 0, 2) !== '62' && strlen($phone) >= 10 && strlen($phone) <= 13) {
            $phone = '62' . $phone;
        }
        return $phone;
    }
}