<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour ajouter les headers de sécurité HTTP.
 * Couvre: HSTS, CSP, Referrer-Policy, Permissions-Policy, etc.
 */
class SecurityHeaders
{
    /**
     * Liste des sources de confiance pour CSP.
     */
    protected array $cspDirectives = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'media-src' => ["'self'"],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'upgrade-insecure-requests' => [],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Strict-Transport-Security (HSTS) - 2 ans
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=63072000; includeSubDomains; preload'
        );

        // Content-Security-Policy
        $csp = $this->buildCSP();
        $response->headers->set('Content-Security-Policy', $csp);

        // Referrer-Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy (Feature-Policy)
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), fullscreen=(self)'
        );

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-XSS-Protection (legacy, mais utile pour anciens navigateurs)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Cache-Control pour réponses API sensibles
        if ($request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        // Remove headers that reveal too much
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * Construit la directive CSP.
     */
    protected function buildCSP(): string
    {
        $directives = [];

        foreach ($this->cspDirectives as $directive => $sources) {
            if (empty($sources)) {
                $directives[] = $directive;
            } else {
                $directives[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $directives);
    }
}
