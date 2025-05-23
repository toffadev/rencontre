<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Ajouter des logs pour le debug des WebSockets
        Broadcast::routes(['middleware' => ['web', 'auth']]);

        // Log lorsque le provider est chargé
        Log::info('[BROADCAST] BroadcastServiceProvider chargé');

        require base_path('routes/channels.php');
    }
}
