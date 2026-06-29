<?php
/**
 * FILE: backend/app/Services/Auth/LoginActivityService.php
 * STATUS: BARU
 */


namespace App\Services\Auth;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

/**
 * Mencatat aktivitas login (device, platform, browser, IP) setiap kali user
 * berhasil login, dan menyediakan helper untuk session management
 * (melihat & mencabut device/sesi lain).
 *
 * Catatan: paket "jenssegers/agent" dipakai untuk parsing User-Agent.
 * Jika belum terpasang, fallback manual sederhana dipakai (lihat parseAgentFallback).
 */
class LoginActivityService
{
    public function record(User $user, Request $request, int $tokenId, string $method): LoginActivity
    {
        [$platform, $browser, $deviceName] = $this->parseAgent($request->userAgent());

        return LoginActivity::create([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'method' => $method,
            'device_name' => $deviceName,
            'platform' => $platform,
            'browser' => $browser,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_active_at' => now(),
        ]);
    }

    public function touch(User $user, int $tokenId): void
    {
        LoginActivity::where('user_id', $user->id)
            ->where('token_id', $tokenId)
            ->whereNull('revoked_at')
            ->update(['last_active_at' => now()]);
    }

    public function revoke(User $user, int $tokenId): void
    {
        LoginActivity::where('user_id', $user->id)
            ->where('token_id', $tokenId)
            ->update(['revoked_at' => now()]);
    }

    protected function parseAgent(?string $userAgent): array
    {
        $userAgent ??= '';

        if (class_exists(Agent::class)) {
            $agent = new Agent;
            $agent->setUserAgent($userAgent);

            $platform = $agent->platform() ?: 'Unknown';
            $browser = $agent->browser() ?: 'Unknown';
            $device = $agent->device() ?: $platform;

            return [$platform, $browser, "{$browser} di {$device}"];
        }

        return $this->parseAgentFallback($userAgent);
    }

    /** Fallback parsing sederhana tanpa dependency tambahan. */
    protected function parseAgentFallback(string $userAgent): array
    {
        $platform = 'Unknown';
        $browser = 'Unknown';

        if (str_contains($userAgent, 'Windows')) {
            $platform = 'Windows';
        } elseif (str_contains($userAgent, 'Android')) {
            $platform = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $platform = 'iOS';
        } elseif (str_contains($userAgent, 'Mac OS')) {
            $platform = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        }

        if (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        }

        return [$platform, $browser, "{$browser} di {$platform}"];
    }
}
