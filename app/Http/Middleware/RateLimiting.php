<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de rate limiting avancé avec backoff exponentiel.
 * Différenciation par rôle et par type d'endpoint.
 */
class RateLimiting
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Configuration des limites par type.
     */
    protected array $limits = [
        // Endpoints d'authentification - très restrictifs
        'auth' => [
            'attempts' => 5,
            'decay_minutes' => 1,
            'backoff_multiplier' => 2,
            'max_backoff' => 60,
        ],
        // Utilisateurs standards
        'user' => [
            'attempts' => 200,
            'decay_minutes' => 60,
        ],
        // Admins - plus permissifs
        'admin' => [
            'attempts' => 1000,
            'decay_minutes' => 60,
        ],
        // API externe (comptables, etc.)
        'api_external' => [
            'attempts' => 500,
            'decay_minutes' => 60,
        ],
        // Endpoints sensibles (exports, bulk operations)
        'sensitive' => [
            'attempts' => 20,
            'decay_minutes' => 10,
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $limitConfig = $this->resolveLimitConfig($request);

        // Vérifier si l'utilisateur est en backoff
        if ($this->isInBackoff($key)) {
            $backoffSeconds = $this->getBackoffDuration($key);
            return $this->buildBackoffResponse($backoffSeconds);
        }

        // Vérifier la limite de taux
        if ($this->limiter->tooManyAttempts($key, $limitConfig['attempts'])) {
            // Activer le backoff exponentiel pour les endpoints auth
            if (isset($limitConfig['backoff_multiplier'])) {
                $this->incrementBackoff($key, $limitConfig);
            }

            return $this->buildRateLimitResponse(
                $this->limiter->availableIn($key),
                $limitConfig['attempts']
            );
        }

        $this->limiter->hit($key, $limitConfig['decay_minutes'] * 60);

        // Réinitialiser le backoff si la requête passe
        if (!str_starts_with($request->path(), 'api/auth')) {
            $this->resetBackoff($key);
        }

        $response = $next($request);

        // Ajouter les headers de rate limit
        return $this->addRateLimitHeaders($response, $key, $limitConfig);
    }

    /**
     * Résoudre la configuration de limite selon la requête.
     */
    protected function resolveLimitConfig(Request $request): array
    {
        $path = $request->path();

        // Endpoints d'authentification
        if (str_starts_with($path, 'api/auth/login') || 
            str_starts_with($path, 'api/auth/register')) {
            return $this->limits['auth'];
        }

        // Endpoints sensibles
        if (str_contains($path, 'export') || 
            str_contains($path, 'bulk') || 
            str_contains($path, 'terminate')) {
            return $this->limits['sensitive'];
        }

        // API externe
        if (str_starts_with($path, 'api/external')) {
            return $this->limits['api_external'];
        }

        // Par rôle
        $user = $request->user();
        if ($user) {
            if ($user->hasRole(['admin', 'directeur'])) {
                return $this->limits['admin'];
            }
        }

        return $this->limits['user'];
    }

    /**
     * Génère une clé unique pour la requête.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $userId = $user ? $user->id : 'guest';
        $path = $request->path();
        
        // Pour les endpoints auth, utiliser IP + user agent
        if (str_starts_with($path, 'api/auth')) {
            return sha1($request->ip() . '|' . $request->userAgent());
        }

        return sha1($userId . '|' . $path);
    }

    /**
     * Vérifie si l'utilisateur est en backoff.
     */
    protected function isInBackoff(string $key): bool
    {
        return cache()->has("backoff:{$key}");
    }

    /**
     * Récupère la durée de backoff.
     */
    protected function getBackoffDuration(string $key): int
    {
        return cache()->get("backoff:{$key}", 0);
    }

    /**
     * Incrémente le backoff exponentiel.
     */
    protected function incrementBackoff(string $key, array $config): void
    {
        $currentBackoff = cache()->get("backoff:{$key}", 0);
        $attempts = cache()->get("backoff_attempts:{$key}", 0) + 1;
        
        $newBackoff = $currentBackoff === 0 
            ? $config['backoff_multiplier'] 
            : min($currentBackoff * $config['backoff_multiplier'], $config['max_backoff']);

        cache()->put("backoff:{$key}", $newBackoff, now()->addMinutes($newBackoff));
        cache()->put("backoff_attempts:{$key}", $attempts, now()->addHours(24));
    }

    /**
     * Réinitialise le backoff.
     */
    protected function resetBackoff(string $key): void
    {
        cache()->forget("backoff:{$key}");
        cache()->forget("backoff_attempts:{$key}");
    }

    /**
     * Construit la réponse de rate limit.
     */
    protected function buildRateLimitResponse(int $retryAfter, int $maxAttempts): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Construit la réponse de backoff.
     */
    protected function buildBackoffResponse(int $backoffSeconds): Response
    {
        return response()->json([
            'success' => false,
            'message' => "Accès temporairement bloqué. Attendez {$backoffSeconds} secondes.",
            'backoff_seconds' => $backoffSeconds,
            'type' => 'backoff',
        ], 429)->withHeaders([
            'Retry-After' => $backoffSeconds,
        ]);
    }

    /**
     * Ajoute les headers de rate limit à la réponse.
     */
    protected function addRateLimitHeaders(Response $response, string $key, array $config): Response
    {
        $remaining = max(0, $config['attempts'] - $this->limiter->attempts($key));
        
        $response->headers->set('X-RateLimit-Limit', $config['attempts']);
        $response->headers->set('X-RateLimit-Remaining', $remaining);

        return $response;
    }
}
