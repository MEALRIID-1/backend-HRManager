<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Charger les rôles de l'utilisateur
        $user->load('roles');

        // Normaliser les rôles demandés (pour supporter slug, nom, et variantes)
        $requiredRoles = array_map(function ($role) {
            $normalized = strtolower(trim($role));
            return $normalized;
        }, $roles);

        // Vérifier si l'utilisateur a l'un des rôles requis
        foreach ($user->roles as $userRole) {
            $userRoleName = strtolower(trim($userRole->name ?? ''));
            $userRoleSlug = strtolower(trim($userRole->slug ?? ''));
            
            if (in_array($userRoleName, $requiredRoles) || in_array($userRoleSlug, $requiredRoles)) {
                return $next($request);
            }
            
            // Support for alternative names (e.g., Administrateur -> admin, Ressources Humaines -> rh)
            if (str_contains($userRoleName, 'administrateur') && in_array('admin', $requiredRoles)) {
                return $next($request);
            }
            if (str_contains($userRoleName, 'ressources humaines') && in_array('rh', $requiredRoles)) {
                return $next($request);
            }
            if (str_contains($userRoleName, 'directeur') && (in_array('admin', $requiredRoles) || in_array('directeur', $requiredRoles))) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Rôle requis: ' . implode(', ', $roles),
        ], 403);
    }
}
