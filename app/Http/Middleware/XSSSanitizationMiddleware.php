<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XSSSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->sanitizeInput($request);

        return $next($request);
    }

    /**
     * Sanitize all string inputs recursively.
     *
     * @param Request $request
     * @return void
     */
    private function sanitizeInput(Request $request): void
    {
        $input = $request->all();

        $sanitized = $this->sanitizeArray($input);

        $request->merge($sanitized);
    }

    /**
     * Recursively sanitize array values.
     *
     * @param array $data
     * @return array
     */
    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }

        return $data;
    }

    /**
     * Sanitize a string value by escaping HTML entities.
     *
     * @param string $string
     * @return string
     */
    private function sanitizeString(string $string): string
    {
        // Remove null bytes
        $string = str_replace("\0", '', $string);

        // Escape HTML entities
        $string = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove potentially dangerous JavaScript protocols
        $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'mocha:', 'livescript:'];
        $string = str_ireplace($dangerousProtocols, '', $string);

        return $string;
    }
}
