<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
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

        if (! empty($user->status) && $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang tidak aktif.',
            ], 403);
        }

        return $next($request);
    }
}
