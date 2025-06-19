<?php

namespace App\Jobs;

use App\Models\ClientNotification;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AwaitingReplyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAwaitingReplyNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */

    /* public function handle(): void
    {
        Log::info('c');

        // Récupérer toutes les conversations (paires client-profil uniques)
        $conversations = Message::select('client_id', 'profile_id')
            ->distinct()
            ->get();

        Log::info('Nombre de conversations trouvées: ' . $conversations->count());

        $clientsNeedingResponse = [];

        foreach ($conversations as $conversation) {
            // Trouver le dernier message de cette conversation
            $lastMessage = Message::where('client_id', $conversation->client_id)
                ->where('profile_id', $conversation->profile_id)
                ->latest()
                ->first();

            // Si le dernier message vient d'un profil (non client), et qu'il a été lu mais pas encore notifié
            if (
                $lastMessage &&
                !$lastMessage->is_from_client &&
                $lastMessage->read_at &&
                ($lastMessage->notification_sent_at === null ||
                    $lastMessage->notification_count < 5) &&
                $lastMessage->read_at <= now()->subMinutes(5)
            ) {

                $clientsNeedingResponse[$lastMessage->client_id] = $lastMessage;
            }
        }

        Log::info('[DEBUG] Clients nécessitant une réponse', ['client_count' => count($clientsNeedingResponse)]);

        // Traiter chaque client nécessitant une réponse
        foreach ($clientsNeedingResponse as $clientId => $message) {
            $user = User::find($clientId);

            if (!$user) {
                Log::warning("Client {$clientId} non trouvé pour notification de message en attente");
                continue;
            }

            // Créer une notification
            $notification = ClientNotification::create([
                'user_id' => $user->id,
                'type' => 'awaiting_reply',
                'message_id' => $message->id,
                'sent_at' => now()
            ]);

            // Envoyer la notification par email
            $user->notify(new AwaitingReplyNotification($message, $notification));

            // Marquer le message comme notifié
            $message->markNotificationSent();

            Log::info("Notification d'attente de réponse envoyée au client {$user->id} pour le message {$message->id}");
        }
    } */

    public function handle(): void
    {
        Log::info('Exécution du job de notification pour messages en attente de réponse');

        // Récupérer toutes les conversations (paires client-profil uniques)
        $conversations = Message::select('client_id', 'profile_id')
            ->distinct()
            ->get();

        Log::info('Nombre de conversations trouvées: ' . $conversations->count());

        $clientsNeedingResponse = [];

        foreach ($conversations as $conversation) {
            // Trouver le dernier message de cette conversation
            $lastMessage = Message::where('client_id', $conversation->client_id)
                ->where('profile_id', $conversation->profile_id)
                ->latest()
                ->first();

            // Si le dernier message vient d'un profil (non client) et n'a pas encore été notifié
            // ou a été notifié moins de 5 fois
            if (
                $lastMessage &&
                !$lastMessage->is_from_client &&
                ($lastMessage->notification_sent_at === null ||
                    $lastMessage->notification_count < 5) &&
                $lastMessage->created_at <= now()->subMinutes(5)
            ) {

                $clientsNeedingResponse[$lastMessage->client_id] = $lastMessage;
            }
        }

        Log::info('[DEBUG] Clients nécessitant une réponse', ['client_count' => count($clientsNeedingResponse)]);

        // Traiter chaque client nécessitant une réponse
        foreach ($clientsNeedingResponse as $clientId => $message) {
            $user = User::find($clientId);

            if (!$user) {
                Log::warning("Client {$clientId} non trouvé pour notification de message en attente");
                continue;
            }

            // Créer une notification
            $notification = ClientNotification::create([
                'user_id' => $user->id,
                'type' => 'awaiting_reply',
                'message_id' => $message->id,
                'sent_at' => now()
            ]);

            // Envoyer la notification par email
            $user->notify(new AwaitingReplyNotification($message, $notification));

            // Marquer le message comme notifié
            $message->markNotificationSent();

            Log::info("Notification d'attente de réponse envoyée au client {$user->id} pour le message {$message->id}");
        }
    }

    /* public function handle(): void
    {
        Log::info('Exécution du job de notification pour messages en attente de réponse');

        // Récupérer toutes les conversations (paires client-profil uniques)
        $conversations = Message::select('client_id', 'profile_id')
            ->distinct()
            ->get();

        Log::info('Nombre de conversations trouvées: ' . $conversations->count());

        $clientsNeedingResponse = [];

        foreach ($conversations as $conversation) {
            // Trouver le dernier message de cette conversation
            $lastMessage = Message::where('client_id', $conversation->client_id)
                ->where('profile_id', $conversation->profile_id)
                ->latest()
                ->first();

            // Si le dernier message vient d'un profil (non client) et n'a pas encore été notifié
            // ou a été notifié moins de 5 fois
            if (
                $lastMessage &&
                !$lastMessage->is_from_client &&
                ($lastMessage->notification_sent_at === null ||
                    $lastMessage->notification_count < 5) &&
                $lastMessage->created_at <= now()->subMinutes(5)
            ) {

                // Vérifier si le client n'est pas en ligne depuis au moins 2 minutes (test)
                // En production, utiliser: now()->subHours(24)
                $user = User::find($lastMessage->client_id);

                if (
                    $user &&
                    ($user->last_activity_at === null ||
                        $user->last_activity_at <= now()->subMinutes(2))
                ) {

                    $clientsNeedingResponse[$lastMessage->client_id] = $lastMessage;
                }
            }
        }

        Log::info('[DEBUG] Clients nécessitant une réponse', ['client_count' => count($clientsNeedingResponse)]);

        // Traiter chaque client nécessitant une réponse
        foreach ($clientsNeedingResponse as $clientId => $message) {
            $user = User::find($clientId);

            if (!$user) {
                Log::warning("Client {$clientId} non trouvé pour notification de message en attente");
                continue;
            }

            // Créer une notification
            $notification = ClientNotification::create([
                'user_id' => $user->id,
                'type' => 'awaiting_reply',
                'message_id' => $message->id,
                'sent_at' => now()
            ]);

            // Envoyer la notification par email
            $user->notify(new AwaitingReplyNotification($message, $notification));

            // Marquer le message comme notifié
            $message->markNotificationSent();

            Log::info("Notification d'attente de réponse envoyée au client {$user->id} pour le message {$message->id}");
        }
    } */
}
