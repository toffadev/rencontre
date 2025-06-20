<?php

namespace App\Services;

use App\Models\ModeratorNotificationRound;
use App\Models\PendingClientMessage;
use App\Models\User;
use App\Notifications\PendingMessageNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ModeratorNotificationService
{
    /**
     * Récupère les modérateurs hors ligne avec le moins de conversations actives
     *
     * @param int $count Nombre de modérateurs à récupérer
     * @param ModeratorNotificationRound|null $lastRound Dernier round de notification (pour exclusion)
     * @return Collection
     */
    public function getModeratorsByWorkload(int $count = 3, ?ModeratorNotificationRound $lastRound = null): Collection
    {
        Log::info('Recherche des modérateurs avec la charge de travail la plus faible');

        // Récupérer tous les modérateurs actifs mais hors ligne
        $query = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', false);

        // Si un round précédent est fourni, exclure les modérateurs déjà notifiés
        if ($lastRound && !empty($lastRound->moderator_ids_notified)) {
            $query->whereNotIn('id', $lastRound->moderator_ids_notified);
        }

        // Récupérer les modérateurs
        $moderators = $query->get();

        // Trier les modérateurs par nombre de conversations actives
        $sortedModerators = $moderators->sortBy(function ($moderator) {
            return $moderator->getActiveConversationsCount();
        });

        // Prendre les N premiers modérateurs
        $selectedModerators = $sortedModerators->take($count);

        Log::info("Sélection de {$selectedModerators->count()} modérateurs sur {$moderators->count()} disponibles");

        return $selectedModerators;
    }

    /**
     * Crée un nouveau round de notification
     *
     * @param int $roundNumber Numéro du round
     * @param array $moderatorIds IDs des modérateurs notifiés
     * @param int $pendingMessagesCount Nombre de messages en attente
     * @return ModeratorNotificationRound
     */
    public function createNotificationRound(int $roundNumber, array $moderatorIds, int $pendingMessagesCount): ModeratorNotificationRound
    {
        Log::info("Création du round de notification #{$roundNumber} pour " . count($moderatorIds) . " modérateurs");

        // Créer le round dans la base de données
        $round = ModeratorNotificationRound::create([
            'round_number' => $roundNumber,
            'moderator_ids_notified' => $moderatorIds,
            'sent_at' => now(),
            'pending_messages_count' => $pendingMessagesCount
        ]);

        Log::info("Round de notification #{$roundNumber} créé avec succès (ID: {$round->id})");

        return $round;
    }

    /**
     * Envoie une notification à un modérateur
     *
     * @param User $moderator Modérateur à notifier
     * @param int $pendingMessagesCount Nombre de messages en attente
     * @return void
     */
    public function sendNotificationToModerator(User $moderator, int $pendingMessagesCount): void
    {
        Log::info("Envoi d'une notification au modérateur #{$moderator->id} ({$moderator->name})");

        try {
            // Envoyer la notification via le système de notification Laravel
            $moderator->notify(new PendingMessageNotification($pendingMessagesCount));

            Log::info("Notification envoyée avec succès au modérateur #{$moderator->id}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de la notification au modérateur #{$moderator->id}: " . $e->getMessage());
        }
    }

    /**
     * Vérifie si les conditions sont remplies pour déclencher un round de notification
     *
     * @return bool
     */
    public function shouldTriggerRound(): bool
    {
        // 1. Compter les modérateurs en ligne
        $onlineModeratorsCount = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->count();

        // 2. Compter les messages clients en attente
        $pendingMessagesCount = PendingClientMessage::where('is_processed', false)
            ->where('is_notified', false)
            ->count();

        // 3. Vérifier les conditions de déclenchement
        // - Aucun modérateur en ligne OU
        // - Nombre de clients en attente > 2x le nombre de modérateurs connectés
        $shouldTrigger = ($onlineModeratorsCount === 0) ||
            ($pendingMessagesCount > ($onlineModeratorsCount * 2));

        Log::info("Vérification des conditions de déclenchement: " .
            ($shouldTrigger ? "DÉCLENCHER" : "NE PAS DÉCLENCHER") .
            " (Modérateurs en ligne: {$onlineModeratorsCount}, Messages en attente: {$pendingMessagesCount})");

        return $shouldTrigger;
    }

    /**
     * Récupère le dernier round de notification
     *
     * @return ModeratorNotificationRound|null
     */
    public function getLastRound(): ?ModeratorNotificationRound
    {
        return ModeratorNotificationRound::latest()->first();
    }

    /**
     * Détermine le numéro du prochain round
     *
     * @return int
     */
    public function getNextRoundNumber(): int
    {
        $lastRound = $this->getLastRound();
        return $lastRound ? $lastRound->round_number + 1 : 1;
    }

    /**
     * Vérifie si un modérateur a répondu après un round de notification
     *
     * @param User $moderator Modérateur à vérifier
     * @param ModeratorNotificationRound $round Round de notification
     * @return bool
     */
    public function hasModeratorResponded(User $moderator, ModeratorNotificationRound $round): bool
    {
        // Si le modérateur est en ligne, il a répondu
        if ($moderator->is_online) {
            return true;
        }

        // Si le modérateur s'est connecté après l'envoi de la notification
        if ($moderator->last_online_at && $round->sent_at) {
            return $moderator->last_online_at->gt($round->sent_at);
        }

        return false;
    }

    /**
     * Compte le nombre de modérateurs qui ont répondu à un round
     *
     * @param ModeratorNotificationRound $round Round de notification
     * @return int
     */
    public function countRespondedModerators(ModeratorNotificationRound $round): int
    {
        $respondedCount = 0;

        // Récupérer les modérateurs notifiés
        $moderators = User::whereIn('id', $round->moderator_ids_notified)->get();

        foreach ($moderators as $moderator) {
            if ($this->hasModeratorResponded($moderator, $round)) {
                $respondedCount++;
            }
        }

        return $respondedCount;
    }
}
