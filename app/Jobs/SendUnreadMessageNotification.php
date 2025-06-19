<?php

namespace App\Jobs;

use App\Models\ClientNotification;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UnreadMessageNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendUnreadMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Exécution du job de notification pour messages non lus');

        // Ajoutez cette ligne pour déboguer les dates
        Log::info('Date de référence pour les messages non lus: ' . now()->subMinutes(1));

        // Récupérer les messages non lus depuis plus de 1 minutes
        // qui n'ont pas encore reçu de notification
        $messages = Message::whereNull('read_at')
            ->whereNull('notification_sent_at')
            ->where('created_at', '<=', now()->subMinutes(1))
            ->where('is_from_client', false)
            ->get();

        Log::info('Messages non lus trouvés: ' . $messages->count(), [
            'messages' => $messages->pluck('id')->toArray(),
            'sql' => Message::whereNull('read_at')
                ->whereNull('notification_sent_at')
                ->where('created_at', '<=', now()->subMinutes(1))
                ->where('is_from_client', false)
                ->toSql() // Ajoutez cette ligne pour voir la requête SQL
        ]);

        foreach ($messages as $message) {
            try {
                $client = $message->client;

                if (!$client) {
                    Log::warning("Message {$message->id} sans client associé");
                    continue;
                }

                Log::info("Envoi de notification pour message non lu", [
                    'message_id' => $message->id,
                    'client_id' => $client->id,
                    'profile_id' => $message->profile_id,
                    'email' => $client->email
                ]);

                // Créer la notification
                $notification = ClientNotification::create([
                    'user_id' => $client->id,
                    'type' => 'unread_message',
                    'message_id' => $message->id,
                    'sent_at' => now(),
                ]);

                // Envoyer l'email
                $client->notify(new UnreadMessageNotification($message, $notification));

                // Marquer le message comme notifié et vérifier
                $message->markNotificationSent();
                Log::info("Message {$message->id} marqué comme notifié : " . ($message->notification_sent_at ? 'OUI' : 'NON'));
            } catch (\Exception $e) {
                Log::error("Erreur lors de l'envoi de notification pour le message {$message->id}: " . $e->getMessage());
            }
        }
    }
}
