<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ Middleware globaux
        $middleware->use([
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // ✅ Ajout du CORS
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // ✅ Alias pour RBAC et Sanctum
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'role'       => \App\Http\Middleware\CheckRole::class,
            // ⚠️ Sanctum v4 n’a plus EnsureFrontendRequestsAreStateful
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
