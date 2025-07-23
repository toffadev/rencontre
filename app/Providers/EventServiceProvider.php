<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use App\Events\MessageSent;
use App\Listeners\MessageListener;
use App\Events\NewClientMessage;
use App\Listeners\ProcessNewClientMessage;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // 🔐 Événements d'authentification
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // 💬 Événements de messagerie (existants)
        MessageSent::class => [
            MessageListener::class,
        ],
        NewClientMessage::class => [
            ProcessNewClientMessage::class,
        ],

        // 🚨 NOUVEAUX : Événements du système réactif de modération
        \App\Events\ModeratorInactivityDetected::class => [
            \App\Listeners\HandleModeratorInactivity::class,
        ],

        // ⚠️ Les ModeratorInactivityWarning sont diffusés via WebSocket uniquement
        // Pas besoin de listener PHP pour ceux-ci sauf pour des métriques
        \App\Events\ModeratorInactivityWarning::class => [
            // Optionnel: ajouter des listeners pour logs/métriques si besoin
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // 🔍 Debug des événements modérateurs en développement
        if (app()->environment('local', 'development')) {
            Event::listen('App\Events\ModeratorInactivity*', function (string $eventName, array $data) {
                \Illuminate\Support\Facades\Log::debug('🔍 Événement modérateur réactif', [
                    'event' => class_basename($eventName),
                    'moderator_id' => $data[0]->moderatorId ?? 'N/A',
                    'profile_id' => $data[0]->profileId ?? 'N/A',
                    'timestamp' => now()
                ]);
            });
        }

        // 📊 Compteurs de métriques temps réel
        Event::listen(\App\Events\ModeratorInactivityDetected::class, function ($event) {
            try {
                // Incrémenter compteur pour tableau de bord (avec TTL de 1 heure)
                $hourKey = 'moderator_timeouts_' . now()->format('H');
                $currentHourCount = Cache::get($hourKey, 0);
                Cache::put($hourKey, $currentHourCount + 1, now()->addHour());

                // Compteur journalier (avec TTL de 24 heures)
                $dayKey = 'moderator_timeouts_' . now()->format('Y-m-d');
                $currentDayCount = Cache::get($dayKey, 0);
                Cache::put($dayKey, $currentDayCount + 1, now()->addDay());
            } catch (\Exception $e) {
                // Ne pas faire planter si le cache n'est pas disponible
                \Illuminate\Support\Facades\Log::warning('Impossible d\'incrémenter les métriques', [
                    'error' => $e->getMessage()
                ]);
            }
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        // Gardez false pour la production pour de meilleures performances
        return false;
    }
}
