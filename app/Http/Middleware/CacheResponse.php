<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour mettre en cache les réponses des endpoints statistiques
 * 
 * Configuration:
   * - ttl: durée de cache en secondes (défaut: 300 = 5min)
 * - tags: tags de cache pour invalidation groupée
 * 
 * Usage dans routes:
 *   Route::get('/dashboard', [DashboardController::class, 'index'])
 *       ->middleware('cache.response:300,dashboard,hr');
 */
class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param int $ttl Durée de cache en secondes (défaut: 300)
     * @param string ...$tags Tags de cache optionnels
     * @return Response
     */
    public function handle(Request $request, Closure $next, int $ttl = 300, string ...$tags): Response
    {
        // Ne pas mettre en cache les requêtes non-GET ou avec paramètres spécifiques
        if (!$request->isMethod('GET') || $request->has('no_cache')) {
            return $next($request);
        }

        // Générer une clé de cache unique basée sur l'URL et les paramètres
        $cacheKey = $this->generateCacheKey($request);

        // Vérifier si la réponse est en cache
        if (Cache::tags($tags)->has($cacheKey)) {
            $cached = Cache::tags($tags)->get($cacheKey);
            
            return response()->json($cached['data'])
                ->withHeaders([
                    'X-Cache' => 'HIT',
                    'X-Cache-TTL' => $cached['expires_at'] - now()->timestamp,
                ]);
        }

        // Exécuter la requête
        $response = $next($request);

        // Mettre en cache uniquement les réponses JSON réussies
        if ($response->isSuccessful() && $response->headers->get('Content-Type') === 'application/json') {
            $data = json_decode($response->getContent(), true);
            
            Cache::tags($tags)->put($cacheKey, [
                'data' => $data,
                'expires_at' => now()->addSeconds($ttl)->timestamp,
            ], $ttl);

            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-TTL', $ttl);
        }

        return $response;
    }

    /**
     * Génère une clé de cache unique pour la requête
     */
    private function generateCacheKey(Request $request): string
    {
        $uri = $request->getRequestUri();
        $userId = auth()->id() ?? 'guest';
        
        // Hash pour une clé plus courte mais unique
        return 'response:' . md5($uri . ':' . $userId);
    }
}
