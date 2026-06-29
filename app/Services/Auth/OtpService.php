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
            Log::info('OtpService::send - Memulai pengiriman OTP', [
                'identifier' => $identifier,
                'purpose' => $purpose,
                'ip' => $ipAddress
            ]);

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

            Log::info('OtpService::send - Kode OTP dibuat', [
                'code' => $code,
                'identifier' => $identifier
            ]);

            $otp = OtpCode::create([
                'identifier' => $identifier,
                'purpose' => $purpose,
                'code' => $code,
                'attempts' => 0,
                'expires_at' => now()->addMinutes($this->expiresMinutes),
                'ip_address' => $ipAddress,
            ]);

            $this->dispatch($identifier, $purpose, $code);

            Log::info('OtpService::send - OTP berhasil dikirim', [
                'otp_id' => $otp->id,
                'identifier' => $identifier
            ]);

            return $otp;
        } catch (\Exception $e) {
            Log::error('OTP send error: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'purpose' => $purpose,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function verify(string $identifier, string $purpose, string $code): array
    {
        try {
            Log::info('OtpService::verify - Memulai verifikasi OTP', [
                'identifier' => $identifier,
                'purpose' => $purpose,
                'code' => $code
            ]);

            if ($purpose === 'phone') {
                $identifier = $this->formatPhoneNumber($identifier);
            }

            $otp = OtpCode::where('identifier', $identifier)
                ->where('purpose', $purpose)
                ->whereNull('verified_at')
                ->latest('created_at')
                ->first();

            if (! $otp) {
                Log::warning('OtpService::verify - OTP tidak ditemukan', [
                    'identifier' => $identifier,
                    'purpose' => $purpose
                ]);
                return ['success' => false, 'message' => 'Kode OTP tidak ditemukan. Silakan minta kode baru.'];
            }

            if ($otp->isExpired()) {
                Log::warning('OtpService::verify - OTP sudah kadaluarsa', [
                    'identifier' => $identifier,
                    'expires_at' => $otp->expires_at
                ]);
                return ['success' => false, 'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.'];
            }

            if ($otp->maxAttemptsReached()) {
                Log::warning('OtpService::verify - Maksimal attempts tercapai', [
                    'identifier' => $identifier,
                    'attempts' => $otp->attempts
                ]);
                return ['success' => false, 'message' => 'Kamu sudah mencapai batas maksimal percobaan. Silakan minta kode baru.'];
            }

            if (! hash_equals($otp->code, $code)) {
                $otp->increment('attempts');
                $sisa = $this->maxAttempts - $otp->attempts;
                Log::warning('OtpService::verify - Kode OTP salah', [
                    'identifier' => $identifier,
                    'attempts' => $otp->attempts,
                    'remaining' => $sisa
                ]);
                return [
                    'success' => false,
                    'message' => $sisa > 0
                        ? "Kode OTP salah. Sisa percobaan: {$sisa}."
                        : 'Kode OTP salah. Kamu sudah mencapai batas maksimal percobaan.',
                ];
            }

            $otp->update(['verified_at' => now()]);
            Log::info('OtpService::verify - OTP berhasil diverifikasi', [
                'identifier' => $identifier,
                'otp_id' => $otp->id
            ]);

            return ['success' => true, 'message' => 'Verifikasi berhasil.'];
        } catch (\Exception $e) {
            Log::error('OTP verify error: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'purpose' => $purpose,
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => 'Terjadi kesalahan saat verifikasi.'];
        }
    }

    protected function dispatch(string $identifier, string $purpose, string $code): void
    {
        Log::info('OtpService::dispatch - Mengirim OTP', [
            'identifier' => $identifier,
            'purpose' => $purpose,
            'code' => $code
        ]);

        if ($purpose === 'google_email') {
            $this->sendEmail($identifier, $code);
            return;
        }
        $this->sendWhatsapp($identifier, $code);
    }

    protected function sendEmail(string $email, string $code): void
    {
        try {
            Log::info('OtpService::sendEmail - Mencoba kirim OTP email', [
                'email' => $email,
                'code' => $code
            ]);

            (new AnonymousNotifiable)
                ->route('mail', $email)
                ->notify(new OtpCodeNotification($code, $this->expiresMinutes));

            Log::info('OtpService::sendEmail - OTP email berhasil dikirim', [
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Send email OTP error: ' . $e->getMessage(), [
                'email' => $email,
                'trace' => $e->getTraceAsString()
            ]);
            // Jangan throw exception agar proses tetap lanjut
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

            Log::info('OtpService::sendWhatsapp - Mencoba kirim OTP WhatsApp', [
                'phone' => $phone,
                'has_token' => !empty($token)
            ]);

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