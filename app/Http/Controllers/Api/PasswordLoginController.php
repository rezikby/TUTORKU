<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\LoginActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

/**
 * PASSWORD LOGIN — khusus Tutor dan Admin (sesuai permintaan: tidak perlu OTP).
 * Siswa TETAP pakai Google OAuth+OTP / Phone OTP (lihat AuthController, tidak diubah).
 *
 * Tutor: email+password ATAU Google (tanpa OTP).
 * Admin: email+password saja.
 *
 * Tutor TIDAK bisa "daftar" sendiri di sini — akun tutor hanya aktif setelah
 * Pengajuan Tutor disetujui admin (lihat Admin\TutorVerificationController),
 * yang otomatis membuatkan password dan mengirimkannya ke email tutor.
 */
class PasswordLoginController extends Controller
{
    public function __construct(protected LoginActivityService $loginActivityService)
    {
    }

    /** POST /api/auth/tutor/login — login tutor dengan email+password. */
    public function tutorLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 422);
        }

        $user->restoreSuspensionIfExpired();
        if ($user->status === 'suspended') {
            return response()->json(array_merge(
                ['suspended' => true],
                $user->getSuspensionPayload(),
            ), 403);
        }

        if (! $user->isTutor()) {
            return response()->json([
                'message' => 'Akun ini bukan akun tutor. Gunakan halaman login siswa.',
            ], 403);
        }

        return $this->issueSession($user, $request, 'password', $validated['remember'] ?? false, $validated['device_name'] ?? null);
    }

    /** POST /api/auth/admin/login — login admin dengan email+password. */
    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 422);
        }

        $user->restoreSuspensionIfExpired();
        if ($user->status === 'suspended') {
            return response()->json(array_merge(
                ['suspended' => true],
                $user->getSuspensionPayload(),
            ), 403);
        }

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Akun ini bukan akun admin.'], 403);
        }

        return $this->issueSession($user, $request, 'password', false, $validated['device_name'] ?? null);
    }

    /**
     * GET /api/auth/tutor/google/redirect — mulai login tutor via Google (tanpa OTP).
     * Beda dengan AuthController::googleRedirectUrl (siswa, yang lanjut ke OTP email).
     */
    public function tutorGoogleRedirectUrl(Request $request)
    {
        /** @var \Laravel\Socialite\Contracts\Provider|\Laravel\Socialite\Two\AbstractProvider $driver */
        $driver = Socialite::driver('google');
        $url = $driver
            ->stateless()
            ->redirectUrl(config('services.google.tutor_redirect'))
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /** GET /api/auth/tutor/google/callback — selesai login Google untuk tutor, langsung tanpa OTP. */
    public function tutorGoogleCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
        $cacheBust = time();

        try {
            /** @var \Laravel\Socialite\Contracts\Provider|\Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver
                ->stateless()
                ->redirectUrl(config('services.google.tutor_redirect'))
                ->user();
        } catch (\Throwable $e) {
            return redirect()->away("{$frontendUrl}/?v={$cacheBust}#/tutor-login?error=google_failed");
        }

        $user = \App\Models\User::where('email', $googleUser->getEmail())->first();

        if (! $user || ! $user->isTutor()) {
            return redirect()->away("{$frontendUrl}/?v={$cacheBust}#/tutor-login?error=not_tutor");
        }

        if ($user->status === 'suspended') {
            $query = ['error' => 'suspended'];
            if ($user->suspended_until) {
                $query['until'] = $user->suspended_until->toIso8601String();
            }
            return redirect()->away("{$frontendUrl}/?v={$cacheBust}#/tutor-login?" . http_build_query($query));
        }

        $user->forceFill([
            'google_id' => $user->google_id ?? $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar(),
        ])->save();

        $token = $user->createToken('TUTORKU-tutor-'.$user->id.'-'.Str::random(6), ['*'], now()->addDays(7));
        $this->loginActivityService->record($user, $request, $token->accessToken->id, 'google');

        // Kirim token lewat query string ke frontend (one-time, langsung dipakai untuk set session).
        $cacheBust = time();
        return redirect()->away("{$frontendUrl}/?v={$cacheBust}#/tutor-login/complete?token={$token->plainTextToken}");
    }

    protected function issueSession(User $user, Request $request, string $method, bool $remember, ?string $deviceName)
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $expiresAt = $remember ? now()->addDays(60) : now()->addDay();
        $tokenName = $deviceName ?: ('TUTORKU-'.$user->id.'-'.Str::random(6));
        $token = $user->createToken($tokenName, ['*'], $expiresAt);

        $this->loginActivityService->record($user, $request, $token->accessToken->id, $method);

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => new UserResource($user->load('settings', 'tutorProfile')),
            'token' => $token->plainTextToken,
        ]);
    }
}
