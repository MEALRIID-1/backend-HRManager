<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Enregistrer l'activité
        $this->logActivity($request, $response);

        return $response;
    }

    /**
     * Enregistrer l'activité dans la base de données.
     */
    private function logActivity(Request $request, Response $response): void
    {
        try {
            $user = Auth::user();

            // Ne pas logger les requêtes GET pour éviter le bruit
            if ($request->isMethod('GET') && $response->getStatusCode() < 400) {
                return;
            }

            $action = $this->getActionFromMethod($request->getMethod());
            $entity = $this->getEntityFromUrl($request->getRequestUri());

            ActivityLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity_name' => $entity,
                'old_value' => null,
                'new_value' => json_encode([
                    'method' => $request->getMethod(),
                    'url' => $request->getRequestUri(),
                    'status' => $response->getStatusCode(),
                ]),
                'timestamp' => now(),
                'ip_address' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging activity: ' . $e->getMessage());
        }
    }

    private function getActionFromMethod(string $method): string
    {
        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'read',
        };
    }

    private function getEntityFromUrl(string $url): string
    {
        // Extraire l'entité de l'URL (ex: /api/v1/users -> users)
        $parts = explode('/', $url);
        $entity = end($parts);

        // Nettoyer les paramètres
        if (str_contains($entity, '?')) {
            $entity = explode('?', $entity)[0];
        }

        return $entity ?: 'unknown';
    }
}
