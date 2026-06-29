<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    /**
     * URIs that should be excluded from CSRF verification.
     *
     * API endpoints use Bearer token authentication, not session cookies,
     * so they don't need CSRF protection. Exclude all API routes to support
     * cross-origin requests from mobile/local IP development.
     *
     * @var string[]
     */
    protected $except = [
        'api/*',
        'broadcasting/auth',
    ];
}
