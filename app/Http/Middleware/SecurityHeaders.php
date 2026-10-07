<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! method_exists($response, 'header') && ! property_exists($response, 'headers')) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        // The Vite dev server needs looser rules, so the content policy is only sent outside local development
        if (! app()->environment('local')) {
            // Analytics hosts are only allowed when the platform has configured analytics (it still loads only after cookie consent)
            $analytics = platform_setting('analytics_snippet_id') ? ' https://www.googletagmanager.com https://www.google-analytics.com' : '';

            $headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
                ."font-src 'self' data: https://fonts.bunny.net; script-src 'self' 'unsafe-inline'{$analytics}; connect-src 'self'{$analytics}; "
                ."frame-ancestors 'self'; form-action 'self' https:; base-uri 'self'";
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, false);
        }

        return $response;
    }
}
