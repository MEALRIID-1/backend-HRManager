<?php

namespace App\Http\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Trait pour utiliser les méthodes de réponse API dans les contrôleurs.
 * 
 * Usage:
 * class MonController extends Controller {
 *     use ApiResponseTrait;
 *     
 *     public function index() {
 *         return $this->success($data);
 *     }
 * }
 */
trait ApiResponseTrait
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function error(string $message = 'Erreur', int $status = 500, array $errors = []): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }

    protected function paginated(AbstractPaginator $paginator, string $message = 'OK', $resourceClass = null): JsonResponse
    {
        return ApiResponse::paginated($paginator, $message, $resourceClass);
    }

    protected function created(mixed $data = null, string $message = 'Ressource créée'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function deleted(string $message = 'Ressource supprimée'): JsonResponse
    {
        return ApiResponse::deleted($message);
    }

    protected function validationError(array $errors, string $message = 'Données invalides'): JsonResponse
    {
        return ApiResponse::validationError($errors, $message);
    }

    protected function unauthorized(string $message = 'Non authentifié'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    protected function forbidden(string $message = 'Accès refusé'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    protected function notFound(string $message = 'Ressource non trouvée'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function conflict(string $message = 'Conflit détecté'): JsonResponse
    {
        return ApiResponse::conflict($message);
    }

    /**
     * Vérifie si l'utilisateur a une permission et retourne une réponse forbidden si non.
     */
    protected function checkPermission(string $permission): ?JsonResponse
    {
        if (!auth()->user()?->can($permission)) {
            return $this->forbidden("Permission '{$permission}' requise.");
        }
        return null;
    }

    /**
     * Vérifie si l'utilisateur a un rôle et retourne une réponse forbidden si non.
     */
    protected function checkRole(string|array $roles): ?JsonResponse
    {
        if (!auth()->user()?->hasRole($roles)) {
            return $this->forbidden('Rôle requis: ' . (is_array($roles) ? implode(', ', $roles) : $roles));
        }
        return null;
    }
}
