<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour vérifier si l'utilisateur a une permission spécifique
 */
class CheckPermission
{
    /**
     * Gérer la requête entrante.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $permission
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user() || !$request->user()->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Permission requise : ' . $permission,
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
