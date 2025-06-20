<?php

namespace App\Jobs;

use App\Models\ModeratorNotificationRound;
use App\Models\PendingClientMessage;
use App\Models\User;
use App\Models\Message;
use App\Notifications\PendingMessageNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPendingMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Démarrage du job ProcessPendingMessages');

        // 1. Compter les modérateurs en ligne
        $onlineModeratorsCount = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->count();
        Log::info("Nombre de modérateurs en ligne: {$onlineModeratorsCount}");

        // 2. Calculer le nombre de messages clients en attente selon la vraie logique métier
        $conversations = \App\Models\Message::select('client_id', 'profile_id')
            ->distinct()
            ->get();
        Log::info('Nombre de conversations trouvées: ' . $conversations->count());

        $pendingConversations = [];
        foreach ($conversations as $conv) {
            $lastMessage = \App\Models\Message::where('client_id', $conv->client_id)
                ->where('profile_id', $conv->profile_id)
                ->orderByDesc('created_at')
                ->first();
            if ($lastMessage && $lastMessage->is_from_client) {
                $pendingConversations[] = [
                    'client_id' => $conv->client_id,
                    'profile_id' => $conv->profile_id,
                    'message_id' => $lastMessage->id,
                ];
            }
        }
        $pendingMessagesCount = count($pendingConversations);
        Log::info("Nombre de messages clients en attente (conversations en attente): {$pendingMessagesCount}", [
            'pending_conversations' => $pendingConversations
        ]);

        if ($pendingMessagesCount === 0) {
            Log::info("Aucun message en attente, fin du traitement");
            return;
        }

        // 3. Vérifier si les conditions de notification sont remplies
        $shouldNotify = $onlineModeratorsCount === 0 ||
            $pendingMessagesCount > ($onlineModeratorsCount * 2);

        if (!$shouldNotify) {
            Log::info("Conditions de notification non remplies (suffisamment de modérateurs en ligne)");
            return;
        }

        // 4. Récupérer le dernier round pour déterminer le numéro du prochain round
        $lastRound = ModeratorNotificationRound::latest()->first();
        $roundNumber = $lastRound ? $lastRound->round_number + 1 : 1;

        // 5. Sélectionner les 3 modérateurs hors ligne avec le moins de conversations actives
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', false);

        // Si nous avons un round précédent, exclure les modérateurs déjà notifiés
        /* if ($lastRound) {
            $moderators->whereNotIn('id', $lastRound->moderator_ids_notified ?? []);
        } */

        // Récupérer les modérateurs avec le moins de conversations actives
        $selectedModerators = $moderators->get()
            ->sortBy(function ($moderator) {
                return $moderator->getActiveConversationsCount();
            })
            ->take(3);

        if ($selectedModerators->isEmpty()) {
            Log::warning("Aucun modérateur disponible pour notification");
            return;
        }

        $moderatorIds = $selectedModerators->pluck('id')->toArray();
        Log::info("Modérateurs sélectionnés pour notification: " . implode(', ', $moderatorIds));

        // 6. Créer un nouveau round de notification
        $round = ModeratorNotificationRound::create([
            'round_number' => $roundNumber,
            'moderator_ids_notified' => $moderatorIds,
            'sent_at' => now(),
            'pending_messages_count' => $pendingMessagesCount
        ]);

        // 7. Envoyer les notifications aux modérateurs sélectionnés
        foreach ($selectedModerators as $moderator) {
            $this->sendNotificationToModerator($moderator, $pendingMessagesCount);
            Log::info("Notification envoyée au modérateur #{$moderator->id} ({$moderator->name})");
        }

        // 8. Marquer les messages comme notifiés
        foreach ($pendingConversations as $pendingConversation) {
            $message = Message::find($pendingConversation['message_id']);
            if ($message) {
                $message->update(['is_notified' => true]);
            }
        }
        Log::info("{$pendingMessagesCount} messages marqués comme notifiés");

        // 9. Planifier la vérification du round dans 30 minutes
        CheckNotificationRound::dispatch($round->id)
            ->delay(Carbon::now()->addMinutes(2));

        Log::info("Vérification du round #{$roundNumber} planifiée dans 30 minutes");
    }

    /* public function handle(): void
    {
        Log::info('Démarrage du job ProcessPendingMessages');

        // 1. Compter les modérateurs en ligne
        $onlineModeratorsCount = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->count();

        Log::info("Nombre de modérateurs en ligne: {$onlineModeratorsCount}");

        // 2. Compter les messages clients en attente
        $pendingMessages = PendingClientMessage::where('is_processed', false)
            ->where('is_notified', false)
            ->get();

        $pendingMessagesCount = $pendingMessages->count();
        Log::info("Nombre de messages clients en attente: {$pendingMessagesCount}");

        if ($pendingMessagesCount === 0) {
            Log::info("Aucun message en attente, fin du traitement");
            return;
        }

        // 3. Vérifier si les conditions de notification sont remplies
        $shouldNotify = $onlineModeratorsCount === 0 ||
            $pendingMessagesCount > ($onlineModeratorsCount * 2);

        if (!$shouldNotify) {
            Log::info("Conditions de notification non remplies (suffisamment de modérateurs en ligne)");
            return;
        }

        // 4. Récupérer le dernier round pour déterminer le numéro du prochain round
        $lastRound = ModeratorNotificationRound::latest()->first();
        $roundNumber = $lastRound ? $lastRound->round_number + 1 : 1;

        // 5. Sélectionner les 3 modérateurs hors ligne avec le moins de conversations actives
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', false);


        // Récupérer les modérateurs avec le moins de conversations actives
        $selectedModerators = $moderators->get()
            ->sortBy(function ($moderator) {
                return $moderator->getActiveConversationsCount();
            })
            ->take(3);

        if ($selectedModerators->isEmpty()) {
            Log::warning("Aucun modérateur disponible pour notification");
            return;
        }

        $moderatorIds = $selectedModerators->pluck('id')->toArray();
        Log::info("Modérateurs sélectionnés pour notification: " . implode(', ', $moderatorIds));

        // 6. Créer un nouveau round de notification
        $round = ModeratorNotificationRound::create([
            'round_number' => $roundNumber,
            'moderator_ids_notified' => $moderatorIds,
            'sent_at' => now(),
            'pending_messages_count' => $pendingMessagesCount
        ]);

        // 7. Envoyer les notifications aux modérateurs sélectionnés
        foreach ($selectedModerators as $moderator) {
            $this->sendNotificationToModerator($moderator, $pendingMessagesCount);
            Log::info("Notification envoyée au modérateur #{$moderator->id} ({$moderator->name})");
        }

        // 8. Marquer les messages comme notifiés
        foreach ($pendingMessages as $pendingMessage) {
            $pendingMessage->update(['is_notified' => true]);
        }
        Log::info("{$pendingMessagesCount} messages marqués comme notifiés");

        // 9. Planifier la vérification du round dans 30 minutes
        CheckNotificationRound::dispatch($round->id)
            ->delay(Carbon::now()->addMinutes(2));

        Log::info("Vérification du round #{$roundNumber} planifiée dans 30 minutes");
    } */

    /**
     * Envoyer une notification à un modérateur
     */
    private function sendNotificationToModerator(User $moderator, int $pendingMessagesCount): void
    {
        $moderator->notify(new PendingMessageNotification($pendingMessagesCount));
    }
}
