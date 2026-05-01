<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $this->handleApiException($e);
            }
        });
    }

    /**
     * Handle API exceptions and return consistent JSON response.
     */
    private function handleApiException(Throwable $e): JsonResponse
    {
        // Validation Exception
        if ($e instanceof ValidationException) {
            return ApiResponse::validationError(
                $e->errors(),
                'Les données fournies sont invalides.'
            );
        }

        // Authentication Exception
        if ($e instanceof AuthenticationException) {
            return ApiResponse::unauthorized('Authentification requise. Veuillez vous connecter.');
        }

        // Authorization Exception
        if ($e instanceof AuthorizationException) {
            return ApiResponse::forbidden('Vous n\'avez pas les permissions nécessaires pour effectuer cette action.');
        }

        // Model Not Found Exception
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return ApiResponse::notFound("{$model} non trouvé.");
        }

        // Not Found HTTP Exception
        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::notFound('Ressource non trouvée.');
        }

        // Method Not Allowed Exception
        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(
                'Méthode HTTP non autorisée.',
                null,
                405
            );
        }

        // Query Exception (Database errors)
        if ($e instanceof QueryException) {
            $errorCode = $e->getCode();
            $message = 'Erreur de base de données.';

            // MySQL error codes
            if ($errorCode === '23000') {
                $message = 'Contrainte de base de données violée. Les données peuvent être en doublon.';
            }

            return ApiResponse::error($message, null, 500);
        }

        // HTTP Exception
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();

            return match ($statusCode) {
                401 => ApiResponse::unauthorized($e->getMessage() ?: 'Non autorisé.'),
                403 => ApiResponse::forbidden($e->getMessage() ?: 'Accès interdit.'),
                404 => ApiResponse::notFound($e->getMessage() ?: 'Ressource non trouvée.'),
                405 => ApiResponse::error('Méthode non autorisée.', null, 405),
                422 => ApiResponse::validationError(null, $e->getMessage() ?: 'Données invalides.'),
                429 => ApiResponse::error('Trop de requêtes. Veuillez réessayer plus tard.', null, 429),
                default => ApiResponse::serverError($e->getMessage() ?: 'Une erreur est survenue.'),
            };
        }

        // Generic Exception
        return ApiResponse::serverError(
            app()->environment('production')
                ? 'Une erreur interne est survenue.'
                : $e->getMessage()
        );
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::unauthorized('Authentification requise.');
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Create a response object from the given validation exception.
     *
     * @param Request $request
     * @param ValidationException $exception
     * @return Response
     */
    protected function convertValidationExceptionToResponse($request, ValidationException $exception): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::validationError(
                $exception->errors(),
                'Les données fournies sont invalides.'
            );
        }

        return parent::convertValidationExceptionToResponse($request, $exception);
    }
}
