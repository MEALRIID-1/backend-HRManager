<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return $this->handleApiException($e, $request);
            }
        });
    }

    protected function handleApiException(Throwable $e, Request $request): JsonResponse
    {
        $status = $this->getStatusCode($e);

        return response()->json([
            'success' => false,
            'message' => $this->getMessage($e),
            'data' => null,
            'errors' => $this->getErrors($e),
        ], $status);
    }

    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return match (get_class($e)) {
            \Illuminate\Auth\AuthenticationException::class => 401,
            \Illuminate\Auth\Access\AuthorizationException::class => 403,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => 404,
            \Illuminate\Validation\ValidationException::class => 422,
            default => 500,
        };
    }

    protected function getMessage(Throwable $e): string
    {
        return match (get_class($e)) {
            \Illuminate\Auth\AuthenticationException::class => 'Unauthorized',
            \Illuminate\Auth\Access\AuthorizationException::class => 'Forbidden',
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class => 'Not Found',
            \Illuminate\Validation\ValidationException::class => 'Validation Error',
            default => 'Internal Server Error',
        };
    }

    protected function getErrors(Throwable $e): ?array
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $e->errors();
        }

        return null;
    }
}