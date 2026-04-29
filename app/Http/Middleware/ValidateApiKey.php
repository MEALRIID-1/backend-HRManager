<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour valider les API keys des services externes.
 * Utilisé pour les endpoints comptables et intégrations.
 */
class ValidateApiKey
{
    /**
     * Configuration des clés API par service.
     */
    protected array $validKeys = [
        'comptable' => [
            'key_prefix' => 'cmp_',
            'permissions' => ['read:employees', 'read:payslips', 'read:contracts'],
            'rate_limit' => 1000,
            'allowed_ips' => [],
        ],
        'audit' => [
            'key_prefix' => 'aud_',
            'permissions' => ['read:all', 'read:audit'],
            'rate_limit' => 500,
            'allowed_ips' => [],
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key manquante.',
                'code' => 'MISSING_API_KEY',
            ], 401);
        }

        // Vérifier le format de la clé
        $serviceType = $this->identifyServiceType($apiKey);
        
        if (!$serviceType) {
            return response()->json([
                'success' => false,
                'message' => 'API key invalide.',
                'code' => 'INVALID_API_KEY',
            ], 401);
        }

        // Vérifier si la clé est révoquée
        if ($this->isKeyRevoked($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API key révoquée.',
                'code' => 'REVOKED_API_KEY',
            ], 401);
        }

        // Vérifier le rate limit pour cette clé
        if ($this->isRateLimited($apiKey, $serviceType)) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limit dépassé pour cette API key.',
                'code' => 'RATE_LIMITED',
                'retry_after' => $this->getRetryAfter($apiKey),
            ], 429);
        }

        // Vérifier whitelist IP si configurée
        if (!$this->isIpAllowed($request, $serviceType)) {
            // Logger la tentative
            $this->logUnauthorizedAccess($request, $apiKey, 'IP_NOT_WHITELISTED');
            
            return response()->json([
                'success' => false,
                'message' => 'IP non autorisée.',
                'code' => 'IP_NOT_WHITELISTED',
            ], 403);
        }

        // Ajouter les informations de la clé à la requête
        $request->attributes->add([
            'api_key_service' => $serviceType,
            'api_key_permissions' => $this->validKeys[$serviceType]['permissions'],
        ]);

        // Logger l'utilisation
        $this->logApiUsage($request, $apiKey, $serviceType);

        return $next($request);
    }

    /**
     * Identifie le type de service à partir de la clé API.
     */
    protected function identifyServiceType(string $apiKey): ?string
    {
        foreach ($this->validKeys as $type => $config) {
            if (str_starts_with($apiKey, $config['key_prefix'])) {
                // Vérifier la clé dans la base de données ou cache
                if ($this->isValidKeyInStorage($apiKey, $type)) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Vérifie si la clé existe en stockage.
     */
    protected function isValidKeyInStorage(string $apiKey, string $type): bool
    {
        // Vérifier dans le cache d'abord
        $cacheKey = "api_key:{$apiKey}";
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey) === $type;
        }

        // Vérifier dans la base de données
        $exists = \DB::table('api_keys')
            ->where('key_hash', hash('sha256', $apiKey))
            ->where('type', $type)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($exists) {
            Cache::put($cacheKey, $type, now()->addMinutes(5));
        }

        return $exists;
    }

    /**
     * Vérifie si la clé est révoquée.
     */
    protected function isKeyRevoked(string $apiKey): bool
    {
        return Cache::has("api_key_revoked:{$apiKey}");
    }

    /**
     * Vérifie le rate limit.
     */
    protected function isRateLimited(string $apiKey, string $serviceType): bool
    {
        $limit = $this->validKeys[$serviceType]['rate_limit'];
        $key = "api_key_rate_limit:{$apiKey}";
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $limit) {
            return true;
        }

        Cache::put($key, $attempts + 1, now()->addMinutes(60));
        
        return false;
    }

    /**
     * Récupère le temps d'attente avant retry.
     */
    protected function getRetryAfter(string $apiKey): int
    {
        $key = "api_key_rate_limit:{$apiKey}";
        $ttl = Cache::get($key . ':ttl');
        
        return $ttl ? max(0, $ttl - time()) : 3600;
    }

    /**
     * Vérifie si l'IP est autorisée.
     */
    protected function isIpAllowed(Request $request, string $serviceType): bool
    {
        $allowedIps = $this->validKeys[$serviceType]['allowed_ips'] ?? [];
        
        if (empty($allowedIps)) {
            return true; // Pas de restriction
        }

        return in_array($request->ip(), $allowedIps);
    }

    /**
     * Log l'utilisation de l'API.
     */
    protected function logApiUsage(Request $request, string $apiKey, string $serviceType): void
    {
        $maskedKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
        
        \Log::channel('api_access')->info('API key usage', [
            'key_mask' => $maskedKey,
            'service' => $serviceType,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Log les accès non autorisés.
     */
    protected function logUnauthorizedAccess(Request $request, string $apiKey, string $reason): void
    {
        $maskedKey = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
        
        \Log::channel('security')->warning('Unauthorized API access attempt', [
            'key_mask' => $maskedKey,
            'reason' => $reason,
            'endpoint' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
