<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Routes that should be audited (sensitive data access).
     *
     * @var array<string>
     */
    protected array $sensitiveRoutes = [
        'contrats/*',
        'fiches-paie/*',
        'employes/*/contrats',
        'employes/*/fiches-paie',
        'export/contrats',
        'export/fiches-paie',
    ];

    /**
     * HTTP methods that should be audited for read operations.
     *
     * @var array<string>
     */
    protected array $auditedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit sensitive routes
        if ($this->shouldAudit($request)) {
            $this->logAccess($request, $response);
        }

        return $response;
    }

    /**
     * Determine if the request should be audited.
     *
     * @param Request $request
     * @return bool
     */
    private function shouldAudit(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->sensitiveRoutes as $pattern) {
            if ($this->matchPattern($path, $pattern)) {
                return in_array($request->method(), $this->auditedMethods, true);
            }
        }

        return false;
    }

    /**
     * Match a URL path against a pattern.
     *
     * @param string $path
     * @param string $pattern
     * @return bool
     */
    private function matchPattern(string $path, string $pattern): bool
    {
        // Convert pattern to regex
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = '#^' . $pattern . '$#';

        return (bool) preg_match($pattern, $path);
    }

    /**
     * Log the access to sensitive data.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    private function logAccess(Request $request, Response $response): void
    {
        $user = $request->user();

        if (!$user) {
            return;
        }

        // Get IP address from request
        $ipAddress = $request->ip();

        // Get user agent
        $userAgent = $request->userAgent();

        // Get route parameters
        $routeParams = $request->route()?->parameters() ?? [];

        // Extract entity info from route
        $entityName = $this->extractEntityName($request->path());
        $entityId = $routeParams['id'] ?? $routeParams['contrat'] ?? $routeParams['fichePaie'] ?? null;

        // Build description
        $action = $this->getActionDescription($request->method(), $entityName);
        $description = "{$action} - IP: {$ipAddress} - User-Agent: {$userAgent}";

        // Log to activity_logs table
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $request->method(),
            'entity_name' => $entityName,
            'entity_id' => $entityId,
            'description' => $description,
            'old_values' => $request->except(['password', 'token', 'authorization']),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Extract entity name from URL path.
     *
     * @param string $path
     * @return string
     */
    private function extractEntityName(string $path): string
    {
        if (str_contains($path, 'contrats')) {
            return 'contrat';
        }

        if (str_contains($path, 'fiches-paie')) {
            return 'fiche_paie';
        }

        if (str_contains($path, 'employes')) {
            return 'employe';
        }

        return 'unknown';
    }

    /**
     * Get a human-readable action description.
     *
     * @param string $method
     * @param string $entityName
     * @return string
     */
    private function getActionDescription(string $method, string $entityName): string
    {
        $actions = [
            'GET' => 'Accès',
            'POST' => 'Création',
            'PUT' => 'Mise à jour',
            'PATCH' => 'Modification',
            'DELETE' => 'Suppression',
        ];

        $action = $actions[$method] ?? $method;

        return "{$action} {$entityName}";
    }
}
