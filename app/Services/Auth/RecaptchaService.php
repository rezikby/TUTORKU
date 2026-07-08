<?php
/**
 * FILE: backend/app/Services/Auth/RecaptchaService.php
 * STATUS: BARU
 */


namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi token Google reCAPTCHA v2/v3 yang dikirim dari frontend.
 * Dipakai di Pengajuan Tutor sesuai instruksi keamanan.
 */
class RecaptchaService
{
    public function verify(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.recaptcha.secret_key');

        // Jika secret belum diisi (belum setup), jangan blokir flow di lingkungan dev/lokal.
        if (! $secret) {
            Log::info('[reCAPTCHA DEV-MODE] secret key belum diisi, verifikasi dilewati.');

            return true;
        }

        if (! $token) {
            Log::warning('[reCAPTCHA] token kosong atau null');
            return false;
        }

        try {
            Log::info('[reCAPTCHA] mengirim verifikasi token ke Google', [
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 20) . '...',
                'ip' => $ip,
            ]);

            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $data = $response->json() ?? [];
            
            $success = (bool) ($data['success'] ?? false);
            
            if (!$success) {
                Log::warning('[reCAPTCHA] verifikasi gagal', [
                    'response_code' => $response->status(),
                    'response_data' => $data,
                    'score' => $data['score'] ?? null,
                    'action' => $data['action'] ?? null,
                    'error_codes' => $data['error-codes'] ?? [],
                    'challenge_ts' => $data['challenge_ts'] ?? null,
                ]);
            } else {
                Log::info('[reCAPTCHA] verifikasi berhasil', [
                    'score' => $data['score'] ?? null,
                    'action' => $data['action'] ?? null,
                    'challenge_ts' => $data['challenge_ts'] ?? null,
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('[reCAPTCHA] verification error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        }
    }
}
