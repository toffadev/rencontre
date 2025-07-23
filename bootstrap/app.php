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
        // 💬 CONSERVÉ : Traitement des messages
        $schedule->command('messages:process')->everyMinute();

        // 🔔 CONSERVÉ : Notifications
        $schedule->command('app:process-notifications')->everyMinute();

        // 📊 CONSERVÉ : Statistiques des modérateurs (mais moins fréquent)
        $schedule->job(new \App\Jobs\CalculateModeratorActivity())->everyTenMinutes();

        // ❌ SUPPRIMÉ : Anciennes tâches de polling remplacées par le système réactif
        // $schedule->call(RotateModeratorProfilesTask)->everyMinute(); // ❌ OBSOLÈTE
        // $schedule->call(ProfileAssignmentMonitoringTask)->everyFifteenSeconds(); // ❌ OBSOLÈTE

        // ✅ NOUVEAU : Système réactif de modération

        // 🧹 Nettoyage périodique des timers expirés (sécurité uniquement)
        $schedule->command('moderator:check-timers --cleanup')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Échec du nettoyage des timers réactifs');
            });

        // 📊 Génération de statistiques des timers
        $schedule->command('moderator:check-timers --stats')
            ->hourly()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/moderator-timer-stats.log'));

        // 🚨 Fallback de sécurité (DÉSACTIVÉ par défaut - seulement si problème avec le système réactif)
        if (config('moderator.enable_fallback_polling', false)) {
            $schedule->command('moderator:check-timers --force')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground()
                ->onFailure(function () {
                    \Illuminate\Support\Facades\Log::critical('CRITIQUE: Fallback de modération échoué');
                    // Ici on pourrait notifier les admins
                });
        }

        // 🧹 Nettoyage des données anciennes
        $schedule->command('moderator:cleanup-old-data')
            ->daily()
            ->at('02:30')
            ->runInBackground();

        // 🗄️ Archivage des métriques
        $schedule->command('moderator:archive-metrics')
            ->weekly()
            ->sundays()
            ->at('03:00')
            ->runInBackground();
    })
    ->withCommands([
        // 📊 CONSERVÉ : Commandes existantes
        \App\Console\Commands\UpdateModeratorStatistics::class,
        \App\Console\Commands\ProcessNotifications::class,
        \App\Console\Commands\ProcessMessages::class,

        // ✅ NOUVEAU : Commandes du système réactif
        \App\Console\Commands\CheckInactivityTimers::class,

        // 🧹 NOUVEAU : Commandes de maintenance (à créer si nécessaire)
        // \App\Console\Commands\CleanupModeratorData::class,
        // \App\Console\Commands\ArchiveModeratorMetrics::class,
    ])
    ->create();
