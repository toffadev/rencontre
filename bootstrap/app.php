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
        $middleware->web([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\TrackUserActivity::class,
            \App\Http\Middleware\TrackModeratorActivity::class,
        ]);

        // API Middleware
        $middleware->api(Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

        // Named Middleware
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
            'client_or_admin' => \App\Http\Middleware\ClientOrAdminMiddleware::class,
            'client_only' => \App\Http\Middleware\ClientOnlyMiddleware::class,
            'broadcast_auth' => \App\Http\Middleware\EnsureBroadcastAuthentication::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function ($schedule) {
        // Exécuter la commande de traitement des messages toutes les minutes
        $schedule->command('messages:process')->everyMinute();
        // Exécuter la commande de traitement des notifications toutes les 15 minutes
        $schedule->command('app:process-notifications')->everyMinute();
        // Enregistrement du job de calcul d'activité des modérateurs
        $schedule->job(new \App\Jobs\CalculateModeratorActivity())->everyFiveMinutes();
        // Ajouter une tâche pour exécuter la rotation des profils toutes les minutes
        $schedule->call(function () {
            $task = app()->make(\App\Tasks\RotateModeratorProfilesTask::class);
            $task();
        })->everyMinute();

        // Ajouter une tâche pour la surveillance des assignations toutes les 15 secondes
        $schedule->call(function () {
            $task = app()->make(\App\Tasks\ProfileAssignmentMonitoringTask::class);
            $task();
        })->everyFifteenSeconds();
    })
    ->withCommands([
        \App\Console\Commands\UpdateModeratorStatistics::class,
        \App\Console\Commands\ProcessNotifications::class,
        \App\Console\Commands\ProcessMessages::class,
    ])
    ->create();
