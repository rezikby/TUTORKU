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
                $headers = [
                    'Access-Control-Allow-Origin' => $origin ?: '*',
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                    'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers', '*'),
                    'Vary' => 'Origin',
                ];

                if ($request->getMethod() === 'OPTIONS') {
                    return response()->json(['status' => 'OK'], 200, $headers);
                }

                $response = $next($request);
                foreach ($headers as $key => $value) {
                    $response->headers->set($key, $value);
                }
                return $response;
            }
        }

        return $next($request);
    }
}
