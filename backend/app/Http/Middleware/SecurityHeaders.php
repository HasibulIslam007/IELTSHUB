<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);
        $response = $next($request);
        $workspace = $request->is('admin', 'admin/*', 'livewire/*');
        $scripts = $workspace ? "'self' 'unsafe-inline' 'unsafe-eval'" : "'self' 'nonce-{$nonce}'";
        $connect = "'self'";
        if (app()->environment('local') && config('hub.frontend_dev')) {
            $scripts .= ' http://127.0.0.1:5173';
            $connect .= ' http://127.0.0.1:5173 ws://127.0.0.1:5173';
        }
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'", "script-src {$scripts}", "style-src 'self' 'unsafe-inline'",
            "connect-src {$connect}", "img-src 'self' data: blob:", "font-src 'self' data:",
            "media-src 'self' blob:", "worker-src 'self' blob:", "object-src 'none'",
            "base-uri 'self'", "form-action 'self'", "frame-ancestors 'none'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(self)');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($request->is('api/*', 'admin', 'admin/*', 'livewire/*', 'attempts/*', 'results/*', 'dashboard', 'settings', 'progress', 'notifications', 'mistakes', 'study-plan', 'onboarding')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
