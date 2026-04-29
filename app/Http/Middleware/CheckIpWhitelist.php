<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour vérifier que l'IP est dans la whitelist.
 * Utilisé pour les routes admin sensibles.
 */
class CheckIpWhitelist
{
    /**
     * IPs whitelistées par défaut (admins du réseau interne).
     */
    protected array $defaultWhitelist = [
        // Format: 'IP' => 'description'
        // À configurer via .env ou database
    ];

    /**
     * Configuration par route.
     */
    protected array $routeConfig = [
        'api/admin/users' => [
            'whitelist_env' => 'ADMIN_IP_WHITELIST',
            'strict' => true,
        ],
        'api/admin/settings' => [
            'whitelist_env' => 'ADMIN_IP_WHITELIST',
            'strict' => true,
        ],
        'api/audit/*' => [
            'whitelist_env' => 'AUDIT_IP_WHITELIST',
            'strict' => true,
        ],
        'api/system/*' => [
            'whitelist_env' => 'SYSTEM_IP_WHITELIST',
            'strict' => true,
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $route = $request->path();

        // Vérifier si la route nécessite une whitelist
        $config = $this->getRouteConfig($route);
        
        if (!$config) {
            return $next($request);
        }

        // Récupérer la whitelist
        $whitelist = $this->getWhitelist($config['whitelist_env']);

        // Vérifier l'IP
        if (!in_array($ip, $whitelist)) {
            $this->logBlockedAccess($request, $route);

            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. IP non autorisée.',
                'code' => 'IP_NOT_WHITELISTED',
            ], 403);
        }

        // Ajouter l'IP aux attributs de la requête pour audit
        $request->attributes->add([
            'whitelisted_ip' => $ip,
            'whitelist_source' => $config['whitelist_env'],
        ]);

        return $next($request);
    }

    /**
     * Récupère la configuration pour une route.
     */
    protected function getRouteConfig(string $route): ?array
    {
        // Vérification exacte
        if (isset($this->routeConfig[$route])) {
            return $this->routeConfig[$route];
        }

        // Vérification avec wildcards
        foreach ($this->routeConfig as $pattern => $config) {
            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('*', '.*', $pattern) . '$#';
                if (preg_match($regex, $route)) {
                    return $config;
                }
            }
        }

        return null;
    }

    /**
     * Récupère la liste des IPs whitelistées.
     */
    protected function getWhitelist(string $envVar): array
    {
        $whitelist = $this->defaultWhitelist;

        // Ajouter les IPs depuis l'environnement
        $envIps = env($envVar, '');
        if (!empty($envIps)) {
            $ips = array_map('trim', explode(',', $envIps));
            $whitelist = array_merge($whitelist, $ips);
        }

        // Supprimer les doublons et vider les valeurs vides
        return array_filter(array_unique($whitelist));
    }

    /**
     * Log l'accès bloqué.
     */
    protected function logBlockedAccess(Request $request, string $route): void
    {
        Log::channel('security')->critical('IP whitelist violation', [
            'ip' => $request->ip(),
            'route' => $route,
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
            'headers' => [
                'referer' => $request->header('Referer'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
            ],
        ]);
    }
}
