<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
            ], 401);
        }

        // Charger les rôles avec leurs permissions
        $user->load('roles.permissions');

        // Vérifier si l'utilisateur a la permission requise
        // Admin a toutes les permissions
        foreach ($user->roles as $role) {
            if (strtolower($role->slug ?? '') === 'admin' || strtolower($role->name ?? '') === 'administrateur') {
                return $next($request);
            }
        }

        // Vérifier les permissions spécifiques
        $hasPermission = false;
        $normalizedPermission = strtolower(trim($permission));
        
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $perm) {
                if (strtolower($perm->nom ?? '') === $normalizedPermission || strtolower($perm->name ?? '') === $normalizedPermission) {
                    $hasPermission = true;
                    break 2;
                }
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Permission requise: ' . $permission,
            ], 403);
        }

        return $next($request);
    }
}
