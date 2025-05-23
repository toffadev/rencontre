<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Base Web Middleware
        $middleware->web(\App\Http\Middleware\HandleInertiaRequests::class);

        // API Middleware
        $middleware->api(Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

        // Named Middleware
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
            'client_or_admin' => \App\Http\Middleware\ClientOrAdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function ($schedule) {
        // Exécuter la commande de traitement des messages toutes les minutes
        $schedule->command('messages:process')->everyMinute();
    })
    ->create();
