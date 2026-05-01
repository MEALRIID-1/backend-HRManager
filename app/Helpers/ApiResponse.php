<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code,
        ], $code);
    }

    /**
     * Error response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $code
     * @return JsonResponse
     */
    public static function error(string $message = 'Error', mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], $code);
    }

    /**
     * Paginated response.
     *
     * @param LengthAwarePaginator $paginator
     * @param string $message
     * @return JsonResponse
     */
    public static function paginated(LengthAwarePaginator $paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'code' => 200,
        ], 200);
    }

    /**
     * Created response.
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    public static function created(mixed $data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * No content response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function noContent(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return self::success(null, $message, 204);
    }

    /**
     * Unauthorized response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, null, 401);
    }

    /**
     * Forbidden response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, null, 403);
    }

    /**
     * Not found response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, null, 404);
    }

    /**
     * Validation error response.
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    public static function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }

    /**
     * Server error response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public static function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return self::error($message, null, 500);
    }
}
