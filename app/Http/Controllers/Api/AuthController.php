<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPhoneOtpRequest;
use App\Http\Requests\Auth\VerifyGoogleOtpRequest;
use App\Http\Requests\Auth\VerifyPhoneOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Auth\LoginActivityService;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected LoginActivityService $loginActivityService,
    ) {}

    public function googleRedirectUrl(Request $request)
    {
        try {
            /** @var \Laravel\Socialite\Contracts\Provider|\Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $url = $driver
                ->stateless()
                ->redirectUrl(config('services.google.redirect'))
                ->redirect()
                ->getTargetUrl();

            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            Log::error('Google redirect error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal mendapatkan URL Google'], 500);
        }
    }

    public function googleCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        Log::info('Google Callback - Memulai proses', [
            'frontend_url' => $frontendUrl
        ]);

        try {
            /** @var \Laravel\Socialite\Contracts\Provider|\Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver
                ->stateless()
                ->redirectUrl(config('services.google.redirect'))
                ->user();

            Log::info('Google Callback - Data user diterima', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'id' => $googleUser->getId()
            ]);
        } catch (\Throwable $e) {
            Log::error('Google callback error: ' . $e->getMessage());
            return redirect()->away("{$frontendUrl}/#/login?error=google_failed");
        }

        if (! $googleUser->getEmail()) {
            Log::error('Google Callback - Email tidak ditemukan');
            return redirect()->away("{$frontendUrl}/#/login?error=google_no_email");
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            Log::info('Google Callback - User ditemukan', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            $user->restoreSuspensionIfExpired();

            if ($user->status === 'suspended') {
                Log::warning('Google Callback - User suspended', [
                    'user_id' => $user->id,
                ]);

                $query = ['error' => 'suspended'];
                if ($user->suspended_until) {
                    $query['until'] = $user->suspended_until->toIso8601String();
                }

                return redirect()->away("{$frontendUrl}/#/login?" . http_build_query($query));
            }

            $shouldSkipOtp = ! empty($user->email_verified_at)
                || ! empty($user->phone_verified_at)
                || ! empty($user->phone);

            // Jika akun sudah terdaftar dan punya verifikasi/nomor telepon, login langsung tanpa OTP
            if ($shouldSkipOtp) {
                Log::info('Google Callback - Akun sudah terdaftar, login langsung', [
                    'user_id' => $user->id,
                    'email_verified_at' => $user->email_verified_at ? true : false,
                    'phone_verified_at' => $user->phone_verified_at ? true : false,
                    'has_phone' => ! empty($user->phone),
                ]);

                $user->forceFill([
                    'last_login_at' => now(),
                    'google_id' => $user->google_id ?? $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                ])->save();

                $token = $user->createToken('auth_token')->plainTextToken;

                return redirect()->away("{$frontendUrl}/#/login/callback?token={$token}&role={$user->role}");
            }

            Log::info('Google Callback - User belum verifikasi email/phone, kirim OTP');
        }

        $pendingToken = (string) Str::uuid();

        Cache::put("google_pending:{$pendingToken}", [
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna TUTORKU',
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
        ], now()->addMinutes(30)); // Tambah waktu menjadi 30 menit

        Log::info('Google Callback - Cache pending dibuat', [
            'pending_token' => $pendingToken,
            'email' => $googleUser->getEmail()
        ]);

        try {
            $otp = $this->otpService->send($googleUser->getEmail(), 'google_email', $request->ip());
            Log::info('OTP berhasil dikirim ke email', [
                'email' => $googleUser->getEmail(),
                'code' => $otp->code,
                'expires_at' => $otp->expires_at
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal kirim OTP Google email: ' . $e->getMessage(), [
                'email' => $googleUser->getEmail(),
                'trace' => $e->getTraceAsString()
            ]);
            // Jangan redirect error, tetap lanjut ke halaman OTP
            // User akan melihat error di frontend
        }

        return redirect()->away("{$frontendUrl}/#/login/google-otp?pending_token={$pendingToken}&email={$googleUser->getEmail()}");
    }

    public function resendGoogleOtp(Request $request)
    {
        try {
            $validated = $request->validate(['pending_token' => ['required', 'string']]);

            Log::info('Resend Google OTP - Memulai', [
                'pending_token' => $validated['pending_token']
            ]);

            $pending = Cache::get("google_pending:{$validated['pending_token']}");

            if (! $pending) {
                Log::warning('Resend Google OTP - Cache tidak ditemukan', [
                    'pending_token' => $validated['pending_token']
                ]);
                return response()->json(['message' => 'Sesi login Google sudah kedaluwarsa. Silakan ulangi dari awal.'], 422);
            }

            try {
                $otp = $this->otpService->send($pending['email'], 'google_email', $request->ip());
                Log::info('OTP baru berhasil dikirim', [
                    'email' => $pending['email'],
                    'code' => $otp->code
                ]);
            } catch (\RuntimeException $e) {
                Log::warning('Resend Google OTP - Rate limited', [
                    'email' => $pending['email'],
                    'message' => $e->getMessage()
                ]);
                return response()->json(['message' => $e->getMessage()], 429);
            }

            return response()->json(['message' => 'Kode OTP baru telah dikirim ke email Google kamu.']);
        } catch (\Exception $e) {
            Log::error('Resend Google OTP error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan pada server'], 500);
        }
    }

    public function verifyGoogleOtp(VerifyGoogleOtpRequest $request)
    {
        try {
            $validated = $request->validated();

            Log::info('Verify Google OTP - Memulai', [
                'pending_token' => $validated['pending_token'],
                'code' => $validated['code']
            ]);

            $pending = Cache::get("google_pending:{$validated['pending_token']}");

            if (! $pending) {
                Log::warning('Verify Google OTP - Cache tidak ditemukan', [
                    'pending_token' => $validated['pending_token']
                ]);
                return response()->json(['message' => 'Sesi login Google sudah kedaluwarsa. Silakan ulangi dari awal.'], 422);
            }

            $result = $this->otpService->verify($pending['email'], 'google_email', $validated['code']);

            if (! $result['success']) {
                Log::warning('Verify Google OTP - Verifikasi gagal', [
                    'email' => $pending['email'],
                    'message' => $result['message']
                ]);
                return response()->json(['message' => $result['message']], 422);
            }

            $user = User::where('email', $pending['email'])->first();

            if (! $user) {
                Log::info('Verify Google OTP - Membuat user baru', [
                    'email' => $pending['email'],
                    'name' => $pending['name']
                ]);

                $user = User::create([
                    'name' => $pending['name'],
                    'email' => $pending['email'],
                    'google_id' => $pending['google_id'],
                    'google_avatar' => $pending['avatar'],
                    'role' => 'siswa',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                UserSetting::create(['user_id' => $user->id]);
            } else {
                Log::info('Verify Google OTP - Update user existing', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                $user->forceFill([
                    'google_id' => $user->google_id ?? $pending['google_id'],
                    'google_avatar' => $pending['avatar'],
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }

            if ($user->status === 'suspended') {
                Log::warning('Verify Google OTP - User suspended', [
                    'user_id' => $user->id
                ]);
                return response()->json(array_merge(
                    ['suspended' => true],
                    $user->getSuspensionPayload(),
                ), 403);
            }

            Cache::forget("google_pending:{$validated['pending_token']}");
            Log::info('Verify Google OTP - Cache pending dihapus', [
                'pending_token' => $validated['pending_token']
            ]);

            return $this->issueSession($user, $request, 'google', $validated['remember'] ?? false, $validated['device_name'] ?? null);
        } catch (\Exception $e) {
            Log::error('Verify Google OTP error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Terjadi kesalahan pada server'], 500);
        }
    }

    public function registerWithPhone(Request $request)
    {
        try {
            Log::info('Register data:', $request->all());

            $validated = $request->validate([
                'phone' => 'required|string|min:10|max:15',
                'name' => 'required|string|max:255',
            ]);

            $phone = $this->formatPhoneNumber($validated['phone']);

            $existingUser = User::where('phone', $phone)->first();
            if ($existingUser) {
                $existingUser->restoreSuspensionIfExpired();
                if ($existingUser->status === 'suspended') {
                    return response()->json(array_merge(
                        ['suspended' => true],
                        $existingUser->getSuspensionPayload(),
                    ), 403);
                }

                $token = $existingUser->createToken('auth_token', ['*'], now()->addDay())->plainTextToken;

                return response()->json([
                    'message' => 'Login berhasil.',
                    'user' => new UserResource($existingUser->load('settings', 'tutorProfile')),
                    'token' => $token,
                    'requires_verification' => false,
                ], 200);
            }

            // Create user but DO NOT mark phone as verified yet. Send OTP for verification.
            $user = User::create([
                'name' => $validated['name'],
                'email' => 'phone_' . Str::random(10) . '@TUTORKU.local',
                'phone' => $phone,
                'role' => 'siswa',
                'status' => 'pending',
            ]);

            UserSetting::create(['user_id' => $user->id]);

            try {
                $this->otpService->send($phone, 'phone', $request->ip());
                Log::info('User created from phone (unverified): ' . $user->id);
            } catch (\RuntimeException $e) {
                Log::error('Failed to send OTP on registration: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Registrasi berhasil, namun pengiriman OTP gagal: ' . $e->getMessage(),
                ], 429);
            }

            // Do not issue token until phone is verified
            return response()->json([
                'message' => 'Registrasi berhasil. Kode OTP telah dikirim ke nomor telepon Anda. Silakan verifikasi untuk menyelesaikan registrasi.',
                'user' => new UserResource($user->load('settings', 'tutorProfile')),
                'requires_verification' => true,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Register error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }

    public function loginWithPhone(Request $request)
    {
        try {
            $request->validate([
                'phone' => 'required|string|min:10',
                'device_name' => 'nullable|string|max:255',
            ]);

            $phone = $this->formatPhoneNumber($request->phone);
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Nomor handphone tidak terdaftar. Silakan daftar terlebih dahulu.',
                'requires_otp' => true,
            ], 404);
        }

        $user->restoreSuspensionIfExpired();
        if ($user->status === 'suspended') {
            return response()->json(array_merge(
                ['suspended' => true],
                $user->getSuspensionPayload(),
            ), 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

            $tokenName = $request->device_name ?: ('TUTORKU-' . $user->id . '-' . Str::random(6));
            $token = $user->createToken($tokenName, ['*'], now()->addDay());

            return response()->json([
                'message' => 'Login berhasil.',
                'user' => new UserResource($user->load('settings', 'tutorProfile')),
                'token' => $token->plainTextToken,
                'role' => $user->role,
            ]);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function sendPhoneOtp(SendPhoneOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $originalPhone = $validated['phone'];
            $phone = $this->formatPhoneNumber($originalPhone);

            Log::info('Sending OTP to phone', [
                'original_phone' => $originalPhone,
                'normalized_phone' => $phone,
            ]);

            try {
                $otp = $this->otpService->send($phone, 'phone', $request->ip());
                Log::info('OTP created', [
                    'code' => $otp->code,
                    'phone' => $phone,
                    'purpose' => 'phone',
                ]);
            } catch (\RuntimeException $e) {
                Log::error('Send OTP runtime error', [
                    'original_phone' => $originalPhone,
                    'normalized_phone' => $phone,
                    'message' => $e->getMessage(),
                ]);
                return response()->json(['message' => 'Gagal mengirim OTP: ' . $e->getMessage()], 500);
            }

            return response()->json([
                'message' => 'Kode OTP telah dikirim via WhatsApp ke nomor telepon kamu.',
                'phone' => $phone,
            ]);
        } catch (\Exception $e) {
            Log::error('Send OTP error', [
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengirim OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPhoneOtp(VerifyPhoneOtpRequest $request)
    {
        try {
            $validated = $request->validated();
            $phone = $this->formatPhoneNumber($validated['phone']);

            Log::info('Verifying OTP for: ' . $phone . ' with code: ' . ($validated['code'] ?? $validated['otp'] ?? ''));

            $result = $this->otpService->verify($phone, 'phone', $validated['code'] ?? $validated['otp']);

            if (! $result['success']) {
                return response()->json(['message' => $result['message']], 422);
            }

            $user = User::where('phone', $phone)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $request->input('name', 'Pengguna TUTORKU'),
                    'email' => 'phone_' . Str::random(10) . '@TUTORKU.local',
                    'phone' => $phone,
                    'role' => 'siswa',
                    'status' => 'active',
                    'phone_verified_at' => now(),
                ]);

                UserSetting::create(['user_id' => $user->id]);

                Log::info('VerifyPhoneOtp - User baru dibuat saat verifikasi', [
                    'phone' => $phone,
                    'user_id' => $user->id,
                ]);
            } else {
                $user->restoreSuspensionIfExpired();
                if ($user->status === 'suspended') {
                    return response()->json(array_merge(
                        ['suspended' => true],
                        $user->getSuspensionPayload(),
                    ), 403);
                }

                $user->forceFill([
                    'phone_verified_at' => $user->phone_verified_at ?? now(),
                    'status' => 'active',
                ])->save();
            }

            if ($user->status === 'suspended') {
                return response()->json(array_merge(
                    ['suspended' => true],
                    $user->getSuspensionPayload(),
                ), 403);
            }

            $tokenName = 'TUTORKU-' . $user->id . '-' . Str::random(6);
            $token = $user->createToken($tokenName, ['*'], now()->addDay());

            return response()->json([
                'success' => true,
                'message' => 'OTP berhasil diverifikasi. Akun aktif.',
                'user' => new UserResource($user->load('settings', 'tutorProfile')),
                'token' => $token->plainTextToken,
                'role' => $user->role,
            ]);
        } catch (\Exception $e) {
            Log::error('Verify OTP error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat verifikasi OTP'
            ], 500);
        }
    }

    protected function issueSession(User $user, Request $request, string $method, bool $remember, ?string $deviceName)
    {
        try {
            $user->forceFill([
                'last_login_at' => now(),
                'remember_login' => $remember,
            ])->save();

            $expiresAt = $remember ? now()->addDays(60) : now()->addDay();

            $tokenName = $deviceName ?: ('TUTORKU-' . $user->id . '-' . Str::random(6));
            $token = $user->createToken($tokenName, ['*'], $expiresAt);

            $this->loginActivityService->record($user, $request, $token->accessToken->id, $method);

            return response()->json([
                'message' => 'Login berhasil.',
                'user' => new UserResource($user->load('settings', 'tutorProfile')),
                'token' => $token->plainTextToken,
                'role' => $user->role,
            ]);
        } catch (\Exception $e) {
            Log::error('Issue session error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan saat login'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $tokenId = $user?->currentAccessToken()?->id;

            if ($tokenId) {
                $this->loginActivityService->revoke($user, $tokenId);
            }

            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Berhasil logout.']);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan saat logout'], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $tokenId = $user->currentAccessToken()?->id;

            if ($tokenId) {
                $this->loginActivityService->touch($user, $tokenId);
            }

            return new UserResource($user->load('settings', 'tutorProfile'));
        } catch (\Exception $e) {
            Log::error('Me error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    public function sessions(Request $request)
    {
        try {
            $currentTokenId = $request->user()->currentAccessToken()?->id;

            $sessions = $request->user()->loginActivities()
                ->whereNull('revoked_at')
                ->latest('last_active_at')
                ->get()
                ->map(function ($activity) use ($currentTokenId) {
                    return [
                        'id' => $activity->id,
                        'device_name' => $activity->device_name,
                        'platform' => $activity->platform,
                        'browser' => $activity->browser,
                        'ip_address' => $activity->ip_address,
                        'method' => $activity->method,
                        'last_active_at' => $activity->last_active_at,
                        'is_current' => $activity->token_id === $currentTokenId,
                    ];
                });

            return response()->json(['data' => $sessions]);
        } catch (\Exception $e) {
            Log::error('Sessions error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan'], 500);
        }
    }

    public function revokeSession(Request $request, int $id)
    {
        try {
            $activity = $request->user()->loginActivities()->whereNull('revoked_at')->findOrFail($id);

            $activity->update(['revoked_at' => now()]);

            if ($activity->token_id) {
                $request->user()->tokens()->where('id', $activity->token_id)->delete();
            }

            return response()->json(['message' => 'Sesi berhasil diakhiri.']);
        } catch (\Exception $e) {
            Log::error('Revoke session error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan'], 500);
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
// masjk