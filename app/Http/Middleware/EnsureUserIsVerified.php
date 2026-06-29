<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        if (! empty($user->email_verified_at) || ! empty($user->phone_verified_at)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Akun Anda belum diverifikasi.',
        ], 403);
    }
}
