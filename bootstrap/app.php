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

    // Make sure your existing middleware stays
     $middleware->api([
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \App\Http\Middleware\LastSeenMiddleware::class,
        \App\Http\Middleware\UpdateLastSeen::class,

    ]);
    // Add your UpdateLastSeen middleware to API routes
    $middleware->api(append: [
        \App\Http\Middleware\UpdateLastSeen::class,
    ]);

    $middleware->web(append: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
    ]);

    $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
})


    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/*'
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
