<?php

namespace App\Listeners;

use App\Events\NewClientMessage;
use App\Events\ProfileAssigned;
use App\Services\ModeratorAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\ModeratorProfileAssignment;
use App\Models\Message;
use App\Models\PendingClientMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessNewClientMessage implements ShouldQueue
{
    use InteractsWithQueue;

    protected $moderatorAssignmentService;

    /**
     * Create the event listener.
     */
    public function __construct(ModeratorAssignmentService $moderatorAssignmentService)
    {
        $this->moderatorAssignmentService = $moderatorAssignmentService;
    }

    /**
     * Handle the event.
     */
    public function handle(NewClientMessage $event): void
    {
        $message = $event->message;
        $lockKey = 'processing_profile_' . $message->profile_id;

        // Créer une entrée dans pending_client_messages
        try {
            PendingClientMessage::create([
                'message_id' => $message->id,
                'client_id' => $message->client_id,
                'profile_id' => $message->profile_id,
                'pending_since' => now(),
                'is_notified' => false,
                'is_processed' => false
            ]);

            Log::info("Message client ajouté à la liste d'attente", [
                'message_id' => $message->id,
                'profile_id' => $message->profile_id,
                'client_id' => $message->client_id
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'ajout du message à la liste d'attente", [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }

        // Utiliser un verrou pour éviter les attributions simultanées
        if (Cache::lock($lockKey, 10)->get()) {
            try {
                // Vérifier si le profil est déjà attribué activement à un modérateur
                $activeAssignment = ModeratorProfileAssignment::where('profile_id', $message->profile_id)
                    ->where('is_active', true)
                    ->first();

                if (!$activeAssignment) {
                    Log::info("Nouveau message reçu pour un profil non attribué", [
                        'profile_id' => $message->profile_id,
                        'client_id' => $message->client_id
                    ]);

                    // Tenter d'attribuer le profil à un modérateur disponible
                    $moderator = $this->moderatorAssignmentService->assignClientToModerator(
                        $message->client_id,
                        $message->profile_id
                    );

                    if ($moderator) {
                        Log::info("Profil attribué automatiquement", [
                            'profile_id' => $message->profile_id,
                            'moderator_id' => $moderator->id
                        ]);

                        // Récupérer tous les messages non lus pour ce profil
                        $unreadMessages = Message::where('profile_id', $message->profile_id)
                            ->where('is_from_client', true)
                            ->whereNull('read_at')
                            ->orderBy('created_at', 'asc')
                            ->get();

                        // Émettre l'événement avec tous les messages non lus
                        /* broadcast(new ProfileAssigned($moderator, $event->profile, [
                            'unread_messages' => $unreadMessages,
                            'total_unread' => $unreadMessages->count()
                        ]))->toOthers(); */

                        broadcast(new ProfileAssigned(
                            $moderator,
                            $event->profile,
                            true, // ou false selon ta logique
                            $message->client_id,
                            $unreadMessages,
                            $unreadMessages->count()
                        ))->toOthers();

                        //broadcast(new ProfileAssigned($moderator, $event->profile, true, $message->client_id))->toOthers();

                        // Marquer le message comme traité dans la liste d'attente
                        PendingClientMessage::where('message_id', $message->id)
                            ->update(['is_processed' => true]);
                    } else {
                        Log::warning("Impossible d'attribuer le profil automatiquement", [
                            'profile_id' => $message->profile_id
                        ]);
                    }
                } else {
                    Log::info("Message reçu pour un profil déjà attribué", [
                        'profile_id' => $message->profile_id,
                        'moderator_id' => $activeAssignment->user_id
                    ]);

                    // Marquer le message comme traité si le profil est déjà attribué
                    PendingClientMessage::where('message_id', $message->id)
                        ->update(['is_processed' => true]);
                }
            } finally {
                Cache::lock($lockKey)->release();
            }
        } else {
            Log::warning("Impossible d'acquérir le verrou pour le traitement du profil", [
                'profile_id' => $message->profile_id
            ]);
        }
    }
}
