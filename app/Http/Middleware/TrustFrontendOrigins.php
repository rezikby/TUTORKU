<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustFrontendOrigins
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', env('FRONTEND_URLS', ''))));

        if ($request->hasHeader('origin')) {
            $origin = $request->header('origin');
            if (in_array($origin, $allowedOrigins, true) || empty($allowedOrigins)) {
                $response = $next($request);
                $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Vary', 'Origin');
                return $response;
            }
        }

        return $next($request);
    }
}
