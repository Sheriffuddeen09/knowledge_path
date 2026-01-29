<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        // ✅ API middleware (Sanctum + last seen tracking)
        $middleware->api([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\UpdateLastSeen::class,
            \App\Http\Middleware\SetCrossOriginHeaders::class,
        ]);

        // ✅ Web middleware
        $middleware->web([
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);

        // ✅ Global middleware
        $middleware->append(
            \Illuminate\Http\Middleware\HandleCors::class
        );

        // ✅ Disable CSRF for API
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
