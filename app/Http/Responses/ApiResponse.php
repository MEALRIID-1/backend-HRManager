<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Classe helper statique pour les réponses API uniformes.
 * 
 * Format de réponse standard:
 * {
 *   "success": true|false,
 *   "message": "...",
 *   "data": {...}|[...]|null,
 *   "meta": {...},       // pour les réponses paginées
 *   "errors": {...}      // pour les erreurs de validation
 * }
 */
class ApiResponse
{
    /**
     * Réponse de succès.
     * 
     * Exemple:
     * ApiResponse::success(['user' => $user], 'Utilisateur créé', 201);
     * 
     * Retourne:
     * {
     *   "success": true,
     *   "message": "Utilisateur créé",
     *   "data": {"user": {...}}
     * }
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Réponse d'erreur.
     * 
     * Exemple:
     * ApiResponse::error('Accès refusé', 403, ['permission' => 'required']);
     * 
     * Retourne:
     * {
     *   "success": false,
     *   "message": "Accès refusé",
     *   "data": null,
     *   "errors": {"permission": "required"}
     * }
     */
    public static function error(string $message = 'Erreur', int $status = 500, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Réponse paginée.
     * 
     * Exemple:
     * ApiResponse::paginated($users, 'Liste des utilisateurs');
     * 
     * Retourne:
     * {
     *   "success": true,
     *   "message": "Liste des utilisateurs",
     *   "data": [...],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 10,
     *     "per_page": 15,
     *     "total": 150,
     *     "from": 1,
     *     "to": 15,
     *     "links": {
     *       "first": "...",
     *       "last": "...",
     *       "prev": null,
     *       "next": "..."
     *     }
     *   }
     * }
     */
    public static function paginated(AbstractPaginator $paginator, string $message = 'OK', $resourceClass = null): JsonResponse
    {
        $data = $resourceClass 
            ? $resourceClass::collection($paginator->items()) 
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
        ]);
    }

    /**
     * Réponse de création réussie (201 Created).
     */
    public static function created(mixed $data = null, string $message = 'Ressource créée'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Réponse de suppression réussie (204 No Content ou 200 avec message).
     */
    public static function deleted(string $message = 'Ressource supprimée'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * Réponse de validation échouée (422).
     */
    public static function validationError(array $errors, string $message = 'Données invalides'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Réponse non autorisée (401).
     */
    public static function unauthorized(string $message = 'Non authentifié'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Réponse accès refusé (403).
     */
    public static function forbidden(string $message = 'Accès refusé'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Réponse ressource non trouvée (404).
     */
    public static function notFound(string $message = 'Ressource non trouvée'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Réponse de conflit (409).
     */
    public static function conflict(string $message = 'Conflit détecté'): JsonResponse
    {
        return self::error($message, 409);
    }
}
