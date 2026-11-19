<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Vite dev server (only when `npm run dev` is running) needs its own origin allowed.
        $dev = is_file(public_path('hot')) ? ' http://localhost:5173 ws://localhost:5173' : '';
        $csp = implode('; ', [
            "default-src 'self'",
            // 'unsafe-eval': Alpine.js standard build; 'wasm-unsafe-eval': SnarkJS/WebAssembly proving. No inline <script> blocks are allowed.
            "script-src 'self' 'unsafe-eval' 'wasm-unsafe-eval'".$dev,
            "script-src-attr 'unsafe-inline'", // confirm() handlers on forms
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$dev,
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'".$dev,
            "worker-src 'self' blob:",
            "frame-ancestors 'none'", "base-uri 'self'", "form-action 'self'", "object-src 'none'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
