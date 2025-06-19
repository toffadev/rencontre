<?php

namespace App\Jobs;

use App\Models\ClientNotification;
use App\Models\User;
use App\Notifications\ReactivationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReactivationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    /* public function handle(): void
    {
        Log::info('Exécution du job de notification pour réactivation');

        // Récupérer la date de référence (5 minutes pour les tests, 48h en production)
        $referenceDate = now()->subMinutes(5);
        Log::info('Date de référence pour inactivité: ' . $referenceDate->toDateTimeString());

        // Logging de la requête SQL
        $query = User::where('type', 'client')
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', $referenceDate);

        // Log de la requête SQL
        $sqlWithBindings = str_replace('?', '%s', $query->toSql());
        $sqlWithBindings = vsprintf($sqlWithBindings, collect($query->getBindings())->map(function ($binding) {
            return is_numeric($binding) ? $binding : "'{$binding}'";
        })->toArray());

        Log::info('Requête SQL pour trouver les clients inactifs: ' . $sqlWithBindings);

        // Exécuter la requête
        $inactiveClients = $query->get();

        Log::info('Clients inactifs trouvés: ' . $inactiveClients->count(), [
            'client_ids' => $inactiveClients->pluck('id')->toArray()
        ]);

        $notificationsSent = 0;
        $notificationsSkipped = 0;

        foreach ($inactiveClients as $client) {
            // Vérifier si une notification de réactivation a déjà été envoyée dans les dernières 48h
            $recentNotification = ClientNotification::where('user_id', $client->id)
                ->where('type', 'reactivation')
                ->where('sent_at', '>=', now()->subMinutes(2))
                ->exists();

            if ($recentNotification) {
                Log::info("Notification ignorée pour le client {$client->id} - déjà notifié récemment");
                $notificationsSkipped++;
                continue;
            }

            Log::info("Envoi de notification de réactivation", [
                'client_id' => $client->id,
                'email' => $client->email,
                'dernière_activité' => $client->last_activity_at
            ]);

            // Créer la notification
            $notification = ClientNotification::create([
                'user_id' => $client->id,
                'type' => 'reactivation',
                'sent_at' => now(),
            ]);

            // Envoyer l'email
            $client->notify(new ReactivationNotification($notification));
            $notificationsSent++;
        }

        Log::info("Résumé de l'exécution du job de réactivation", [
            'clients_inactifs_trouvés' => $inactiveClients->count(),
            'notifications_envoyées' => $notificationsSent,
            'notifications_ignorées' => $notificationsSkipped
        ]);
    } */

    public function handle(): void
    {
        try {
            Log::info('Début du job de notification pour réactivation');

            // Récupérer la date de référence (5 minutes pour les tests, 48h en production)
            $referenceDate = now()->subMinutes(5);
            Log::info('Date de référence pour inactivité: ' . $referenceDate->toDateTimeString());

            // Vérifier manuellement la présence d'utilisateurs qui correspondent aux critères
            $allClients = User::where('type', 'client')->get();
            Log::info('Nombre total de clients: ' . $allClients->count());

            $clientsWithActivity = User::where('type', 'client')
                ->whereNotNull('last_activity_at')
                ->get();
            Log::info('Clients avec activité enregistrée: ' . $clientsWithActivity->count());

            // Logging de la requête SQL
            $query = User::where('type', 'client')
                ->whereNotNull('last_activity_at')
                ->where('last_activity_at', '<=', $referenceDate);

            // Log de la requête SQL
            $sqlWithBindings = str_replace('?', '%s', $query->toSql());
            $sqlWithBindings = vsprintf($sqlWithBindings, collect($query->getBindings())->map(function ($binding) {
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray());

            Log::info('Requête SQL pour trouver les clients inactifs: ' . $sqlWithBindings);

            // Exécuter la requête
            $inactiveClients = $query->get();

            Log::info('Clients inactifs trouvés: ' . $inactiveClients->count(), [
                'client_ids' => $inactiveClients->pluck('id')->toArray()
            ]);

            // Log des valeurs de last_activity_at pour comprendre pourquoi la requête ne trouve rien
            if ($clientsWithActivity->count() > 0) {
                Log::info('Échantillon de dates last_activity_at:', [
                    'dates' => $clientsWithActivity->take(5)->pluck('last_activity_at', 'id')->toArray()
                ]);
            }

            $notificationsSent = 0;
            $notificationsSkipped = 0;

            foreach ($inactiveClients as $client) {
                // Vérifier si une notification de réactivation a déjà été envoyée dans les dernières 48h
                $recentNotification = ClientNotification::where('user_id', $client->id)
                    ->where('type', 'reactivation')
                    ->where('sent_at', '>=', now()->subHours(48))
                    ->exists();

                if ($recentNotification) {
                    Log::info("Notification ignorée pour le client {$client->id} - déjà notifié récemment");
                    $notificationsSkipped++;
                    continue;
                }

                Log::info("Envoi de notification de réactivation", [
                    'client_id' => $client->id,
                    'email' => $client->email,
                    'dernière_activité' => $client->last_activity_at
                ]);

                // Créer la notification
                $notification = ClientNotification::create([
                    'user_id' => $client->id,
                    'type' => 'reactivation',
                    'sent_at' => now(),
                ]);

                // Envoyer l'email
                $client->notify(new ReactivationNotification($notification));
                $notificationsSent++;
            }

            Log::info("Résumé de l'exécution du job de réactivation", [
                'clients_inactifs_trouvés' => $inactiveClients->count(),
                'notifications_envoyées' => $notificationsSent,
                'notifications_ignorées' => $notificationsSkipped
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans le job SendReactivationNotification: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
