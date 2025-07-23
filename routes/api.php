<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestEventController;
use App\Http\Controllers\Client\ProfilePointController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/test-reactivation', function () {
    Log::info('Test manuel du job de réactivation');

    // Forcer l'exécution synchrone
    try {
        $job = new \App\Jobs\SendReactivationNotification();
        $job->handle();
        return 'Job de réactivation exécuté. Vérifiez les logs.';
    } catch (\Exception $e) {
        return 'Erreur: ' . $e->getMessage();
    }
});

// Fichier temporaire pour tester
Route::get('/test-inactivity-simple', function () {
    try {
        // Créer un mock de l'événement sans dépendances
        $event = new \App\Events\ModeratorInactivityDetected(
            1,
            123,
            456,
            'test_manuel'
        );

        // Émettre manuellement aux listeners
        event($event);

        return "Événement émis avec succès (version minimale)";
    } catch (\Exception $e) {
        return "Erreur: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
    }
});


Route::get('/test-inactivity-real', function () {
    try {
        $service = app(\App\Services\ModeratorActivityService::class);
        $service->checkInactivity();
        return "Vérification d'inactivité exécutée";
    } catch (\Exception $e) {
        return "Erreur: " . $e->getMessage();
    }
});

Route::get('/test-listener-registration', function () {
    try {
        // Vérifier l'enregistrement du listener
        $listeners = app('events')->getListeners('App\Events\ModeratorInactivityDetected');

        Log::info('🔍 DIAGNOSTIC - Vérification enregistrement listener', [
            'event_class' => 'App\Events\ModeratorInactivityDetected',
            'registered_listeners' => count($listeners),
            'listeners_details' => array_map(function ($listener) {
                if (is_array($listener)) {
                    return get_class($listener[0] ?? null) . '::' . ($listener[1] ?? 'unknown');
                }
                return is_object($listener) ? get_class($listener) : (string)$listener;
            }, $listeners),
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
        ]);

        return [
            'status' => 'success',
            'listeners_registered' => count($listeners),
            'listeners' => array_map(function ($listener) {
                if (is_array($listener)) {
                    return get_class($listener[0] ?? null) . '::' . ($listener[1] ?? 'unknown');
                }
                return is_object($listener) ? get_class($listener) : (string)$listener;
            }, $listeners),
            'queue_driver' => config('queue.default'),
            'expected_listener' => 'App\Listeners\HandleModeratorInactivity'
        ];
    } catch (\Exception $e) {
        Log::error('❌ Erreur diagnostic listener', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
});
Route::get('/test-listener-with-real-data', function () {
    try {
        // Récupérer une assignation active
        $assignment = \App\Models\ModeratorProfileAssignment::where('is_active', true)
            ->first();

        if (!$assignment) {
            return ['status' => 'error', 'message' => 'Aucune assignation active trouvée'];
        }

        // Récupérer le modérateur
        $moderator = \App\Models\User::find($assignment->user_id);

        if (!$moderator) {
            return ['status' => 'error', 'message' => 'Modérateur introuvable'];
        }

        // Simuler un ancien modérateur
        $oldModeratorId = $moderator->id - 1;
        if ($oldModeratorId < 1) $oldModeratorId = $moderator->id + 1;

        // Émettre l'événement avec les paramètres corrects
        event(new \App\Events\ProfileAssigned(
            $moderator,
            $assignment->profile_id,
            $assignment->id,
            $oldModeratorId,
            'test'
        ));

        return [
            'status' => 'success',
            'message' => 'Événement ProfileAssigned émis avec succès',
            'moderator_id' => $moderator->id,
            'profile_id' => $assignment->profile_id,
            'assignment_id' => $assignment->id,
            'old_moderator_id' => $oldModeratorId,
            'reason' => 'test'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
});
Route::get('/test-listener-sync', function () {
    try {
        // Créer une instance du listener directement
        $listener = app(\App\Listeners\HandleModeratorInactivity::class);

        // Récupérer une assignation pour le test
        $assignment = \App\Models\ModeratorProfileAssignment::where('is_active', true)->first();

        if (!$assignment) {
            return [
                'status' => 'error',
                'message' => 'Aucune assignation active trouvée pour le test'
            ];
        }

        Log::info('🧪 TEST SYNCHRONE - Appel direct du listener', [
            'assignment_id' => $assignment->id,
            'moderator_id' => $assignment->user_id,
            'profile_id' => $assignment->profile_id
        ]);

        // Créer l'événement
        $event = new \App\Events\ModeratorInactivityDetected(
            $assignment->user_id,
            $assignment->profile_id,
            null,
            $assignment->id,
            'test_synchrone_direct'
        );

        // Appeler directement la méthode handle
        $listener->handle($event);

        Log::info('✅ TEST SYNCHRONE - Listener exécuté directement');

        return [
            'status' => 'success',
            'message' => 'Listener exécuté directement en mode synchrone',
            'listener_class' => get_class($listener)
        ];
    } catch (\Exception $e) {
        Log::error('❌ Erreur test synchrone listener', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
});
Route::get('/test-event-service-provider', function () {
    try {
        // Vérifier si EventServiceProvider est bien configuré
        $eventServiceProvider = app()->getLoadedProviders()['App\Providers\EventServiceProvider'] ?? null;

        $result = [
            'event_service_provider_loaded' => $eventServiceProvider !== null,
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'broadcast_driver' => config('broadcasting.default'),
        ];

        // Essayer de récupérer la configuration des listeners depuis EventServiceProvider
        if (file_exists(app_path('Providers/EventServiceProvider.php'))) {
            $content = file_get_contents(app_path('Providers/EventServiceProvider.php'));
            $result['eventserviceprovider_mentions_handlemoderatorinactivity'] =
                strpos($content, 'HandleModeratorInactivity') !== false;
            $result['eventserviceprovider_mentions_moderatorinactivitydetected'] =
                strpos($content, 'ModeratorInactivityDetected') !== false;
        }

        Log::info('🔍 DIAGNOSTIC - Configuration EventServiceProvider', $result);

        return $result;
    } catch (\Exception $e) {
        Log::error('❌ Erreur diagnostic EventServiceProvider', [
            'error' => $e->getMessage()
        ]);

        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
});
Route::get('/test-queue-processing', function () {
    try {
        $queueDriver = config('queue.default');

        Log::info('🔍 DIAGNOSTIC - Configuration queue', [
            'queue_driver' => $queueDriver,
            'high_queue_connection' => config("queue.connections.{$queueDriver}"),
        ]);

        // Vérifier les jobs en queue
        $result = [
            'queue_driver' => $queueDriver,
            'message' => 'Configuration queue affichée dans les logs'
        ];

        // Si c'est database queue, on peut vérifier la table jobs
        if ($queueDriver === 'database') {
            $pendingJobs = DB::table('jobs')->where('queue', 'high')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $result['pending_high_priority_jobs'] = $pendingJobs;
            $result['failed_jobs'] = $failedJobs;
        }

        return $result;
    } catch (\Exception $e) {
        Log::error('❌ Erreur diagnostic queue', [
            'error' => $e->getMessage()
        ]);

        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
});
Route::get('/test-emit-inactivity', function () {
    try {
        // Récupérer une assignation active
        $assignment = \App\Models\ModeratorProfileAssignment::where('is_active', true)
            ->first();

        if (!$assignment) {
            return ['status' => 'error', 'message' => 'Aucune assignation active trouvée'];
        }

        // Émettre l'événement directement
        \Illuminate\Support\Facades\Log::info('Émission directe de ModeratorInactivityDetected', [
            'moderator_id' => $assignment->user_id,
            'profile_id' => $assignment->profile_id
        ]);

        event(new \App\Events\ModeratorInactivityDetected(
            $assignment->user_id,
            $assignment->profile_id,
            null,
            $assignment->id,
            'test_direct'
        ));

        return [
            'status' => 'success',
            'message' => 'Événement émis directement',
            'moderator_id' => $assignment->user_id,
            'profile_id' => $assignment->profile_id
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
});

//app/Services/TimeoutManagementService.php
// Route pour créer un timer de test
Route::get('/test-start-timer', function () {
    try {
        $service = app(\App\Services\TimeoutManagementService::class);
        $timer = $service->startInactivityTimer(1, 123, 456);

        return response()->json([
            'status' => 'success',
            'timer' => $timer,
            'message' => 'Timer démarré avec succès'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Route pour vérifier les timers actifs
Route::get('/test-active-timers', function () {
    try {
        $service = app(\App\Services\TimeoutManagementService::class);
        $timers = $service->getActiveTimersData();
        $stats = $service->getTimerStats();

        return response()->json([
            'status' => 'success',
            'active_timers' => $timers,
            'stats' => $stats
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Route pour forcer la vérification des timers
Route::get('/test-check-timers', function () {
    try {
        $service = app(\App\Services\TimeoutManagementService::class);
        $result = $service->checkTimers();

        return response()->json([
            'status' => 'success',
            'result' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/test-full-cycle', function () {
    $service = app(\App\Services\TimeoutManagementService::class);

    // 1. Démarrer un timer (10 secondes)
    $timer = $service->startInactivityTimer(1, 999, 888);
    Log::info("Timer démarré", ['timer' => $timer]);

    // 2. Vérifier immédiatement
    $stats = $service->getTimerStats();
    Log::info("Stats immédiates", ['stats' => $stats]);
    if ($stats['expired'] > 0) {
        Log::error("ERREUR: Timer expiré immédiatement!");
        return "ERREUR: Le timer est marqué comme expiré dès sa création";
    }

    // 3. Attendre 15 secondes
    sleep(15);

    // 4. Vérifier expiration
    $result = $service->checkTimers();
    Log::info("Résultat checkTimers", ['result' => $result]);

    if ($result['expired'] === 1) {
        Log::info("SUCCÈS: Timer a expiré comme prévu");
        return "SUCCÈS: Le système a bien détecté l'expiration du timer";
    } else {
        Log::error("ERREUR: Expiration non détectée");
        return "ERREUR: L'expiration n'a pas été détectée";
    }
});

Route::get('/test-timer-fix', function () {
    $service = app(\App\Services\TimeoutManagementService::class);

    // 1. Démarrer un timer
    $timer = $service->startInactivityTimer(1, 100, 200);

    // 2. Vérifier immédiatement
    $stats = $service->getTimerStats();
    if ($stats['expired'] > 0) {
        return response()->json([
            'status' => 'error',
            'message' => 'Timer toujours marqué comme expiré',
            'stats' => $stats,
            'timer' => $timer
        ]);
    }

    return response()->json([
        'status' => 'success',
        'message' => 'Timer actif comme attendu',
        'stats' => $stats,
        'timer' => $timer
    ]);
});

Route::get('/test-time-calculation', function () {
    $now = now();
    $future = now()->addMinutes(2);

    return response()->json([
        'diffInSeconds_correct' => $future->diffInSeconds($now, false), // Doit être positif
        'diffInSeconds_incorrect' => $now->diffInSeconds($future, false) // Doit être négatif
    ]);
});

Route::get('/test-full-workflow', function () {
    $service = app(\App\Services\TimeoutManagementService::class);

    // 1. Créer un timer court (30 secondes)
    $timer = $service->startInactivityTimer(1, 100, 200);
    Log::info("Timer créé", $timer);

    // 2. Vérifier qu'il n'est pas expiré
    $stats = $service->getTimerStats();
    if ($stats['expired'] !== 0) {
        return "ÉCHEC: Timer marqué comme expiré trop tôt";
    }

    // 3. Attendre expiration
    sleep(35);

    // 4. Vérifier expiration
    $result = $service->checkTimers();
    if ($result['expired'] === 1) {
        return "SUCCÈS: Timer a expiré et a été traité correctement";
    } else {
        return "ÉCHEC: Expiration non détectée";
    }
});
//Fin fichier temporaire pour tester

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route pour tester l'envoi d'événements
Route::post('/test-event', [TestEventController::class, 'sendTestEvent']);

// Dans routes/api.php
Route::get('/auth-test', function (Request $request) {
    return [
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'session_id' => session()->getId(),
        'cookies' => $request->cookies->all(),
    ];
})->middleware('web');

// Stripe webhook route for profile points
Route::post('/stripe/profile-points/webhook', [ProfilePointController::class, 'handleWebhook'])
    ->name('stripe.profile-points.webhook')
    ->withoutMiddleware(['csrf']);

// Route de diagnostic pour les modérateurs
Route::middleware(['auth:sanctum', 'moderator'])->get('/moderateur/diagnostic', [App\Http\Controllers\Moderator\ModeratorController::class, 'diagnosticStatus']);
