<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\EmailLoginCodeNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginCodeController extends Controller
{
    public function requestCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20'],
            'role' => ['required', 'in:siswa,tutor'],
        ]);

        $email = $validated['email'] ?? null;
        $phone = $validated['phone'] ?? null;
        $role = $validated['role'];

        $user = null;
        if ($email) {
            $user = User::firstWhere('email', $email);
        }
        if (! $user && $phone) {
            $user = User::firstWhere('phone', $phone);
        }

        if (! $user) {
            $user = User::create([
                'name' => $email ? explode('@', $email)[0] : 'Pengguna ' . Str::random(4),
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'password' => Hash::make(Str::random(16)),
                'status' => 'active',
            ]);

            if ($role === 'tutor') {
                $user->tutorProfile()->create(['registration_step' => 1]);
            }
            $user->settings()->create();
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $loginCode = LoginCode::create([
            'user_id' => $user->id,
            'email' => $email,
            'phone' => $phone,
            'code' => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        if ($email) {
            $user->notify(new EmailLoginCodeNotification($loginCode));
        }

        RateLimiter::hit($this->throttleKey($request, 'request'), 60);

        return response()->json(['message' => 'Kode verifikasi sudah dikirim.']);
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:5'],
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey($request, 'verify'), 10)) {
            return response()->json(['message' => 'Terlalu banyak percobaan verifikasi. Silakan coba lagi nanti.'], 429);
        }

        $loginCode = LoginCode::where('code', $validated['code'])
            ->whereNull('used_at')
            ->where(function ($query) use ($validated) {
                if ($validated['email']) {
                    $query->where('email', $validated['email']);
                }
                if ($validated['phone']) {
                    $query->where('phone', $validated['phone']);
                }
            })
            ->latest()
            ->first();

        if (! $loginCode || $loginCode->isExpired()) {
            RateLimiter::hit($this->throttleKey($request, 'verify'), 60);
            return response()->json(['message' => 'Kode tidak valid atau sudah kedaluwarsa.'], 422);
        }

        $loginCode->update(['used_at' => now()]);

        $user = $loginCode->user;
        if (! $user) {
            RateLimiter::hit($this->throttleKey($request, 'verify'), 60);
            return response()->json(['message' => 'Akun tidak ditemukan.'], 404);
        }

        $user->restoreSuspensionIfExpired();
        if ($user->status === 'suspended') {
            return response()->json(array_merge(
                ['suspended' => true],
                $user->getSuspensionPayload(),
            ), 403);
        }

        $token = $user->createToken('TUTORKU-'.$user->id)->plainTextToken;
        RateLimiter::clear($this->throttleKey($request, 'verify'));

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => new UserResource($user->load('settings', 'tutorProfile')),
        ]);
    }

    protected function throttleKey(Request $request, string $type = 'request'): string
    {
        $identifier = $request->input('email') ?: $request->input('phone') ?: $request->ip();
        return sprintf('login-code:%s:%s:%s', $type, $identifier, $request->ip());
    }
}
