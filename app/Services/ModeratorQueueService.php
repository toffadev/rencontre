<?php

namespace App\Services;

use App\Models\User;
use App\Models\ModeratorProfileAssignment;
use App\Models\ModeratorQueue;
use App\Events\ModeratorQueuePositionChanged;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Message;
use App\Models\Profile;
use App\Models\ProfileLock;

/**
 * Service pour gérer la file d'attente des modérateurs.
 * 
 * Ce service permet de :
 * - ajouter ou retirer un modérateur de la file d'attente,
 * - gérer la priorité et la position des modérateurs dans la file,
 * - attribuer automatiquement des profils disponibles aux modérateurs en attente,
 * - notifier les modérateurs lors de changements de position,
 * - calculer le temps d'attente estimé pour chaque modérateur,
 * - obtenir l'état actuel de la file d'attente.
 * 
 * L'objectif est d'organiser efficacement la répartition des profils à modérer
 * en fonction de la disponibilité et de la priorité des modérateurs.
 */
class ModeratorQueueService
{
    /**
     * Ajouter un modérateur à la file d'attente
     */
    public function addToQueue($moderatorId, $priority = 0)
    {
        // Vérifier si le modérateur est déjà dans la file d'attente
        $existingQueue = ModeratorQueue::where('moderator_id', $moderatorId)->first();

        if ($existingQueue) {
            // Mettre à jour la priorité si nécessaire
            if ($priority > $existingQueue->priority) {
                $existingQueue->priority = $priority;
                $existingQueue->save();
            }

            // Réorganiser la file d'attente
            $this->reorderQueue();

            return $existingQueue;
        }

        // Créer une nouvelle entrée dans la file d'attente
        $queue = new ModeratorQueue([
            'moderator_id' => $moderatorId,
            'queued_at' => now(),
            'priority' => $priority,
            'position' => $this->getLastPosition() + 1,
            'estimated_wait_time' => $this->calculateEstimatedWaitTime()
        ]);

        $queue->save();

        // Mettre à jour la position dans l'assignation
        ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->update(['queue_position' => $queue->position]);

        // Émettre un événement pour notifier le modérateur
        $this->notifyQueuePositionChanged($moderatorId);

        // Réorganiser la file d'attente
        $this->reorderQueue();

        return $queue;
    }

    /**
     * Retirer un modérateur de la file d'attente
     */
    public function removeFromQueue($moderatorId)
    {
        $queue = ModeratorQueue::where('moderator_id', $moderatorId)->first();

        if (!$queue) {
            return false;
        }

        $position = $queue->position;
        $queue->delete();

        // Mettre à jour la position dans l'assignation
        ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->update(['queue_position' => null]);

        // Mettre à jour les positions des autres modérateurs
        ModeratorQueue::where('position', '>', $position)
            ->decrement('position');

        // Notifier les modérateurs des changements de position
        $this->notifyAllQueueChanges();

        return true;
    }

    /**
     * Obtenir la position d'un modérateur dans la file d'attente
     */
    public function getQueuePosition($moderatorId)
    {
        $queue = ModeratorQueue::where('moderator_id', $moderatorId)->first();

        if (!$queue) {
            return null;
        }

        return $queue->position;
    }


    public function processQueue($assignedModerators = [])
    {
        // Vérifier s'il y a des profils disponibles
        $availableProfiles = $this->getAvailableProfiles();

        // Ajouter les profils avec messages en attente
        $profilesWithPendingMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->select('profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        foreach ($profilesWithPendingMessages as $profileId) {
            if (!in_array($profileId, $availableProfiles)) {
                $availableProfiles[] = $profileId;
            }
        }

        if (empty($availableProfiles)) {
            Log::info("Aucun profil disponible pour attribution");
            return [
                'processed_assignments' => 0,
                'remaining_in_queue' => ModeratorQueue::count()
            ];
        }

        // Récupérer les modérateurs en attente
        $queuedModerators = ModeratorQueue::orderBy('position')
            ->with('moderator')
            ->get();

        if ($queuedModerators->isEmpty()) {
            Log::info("Aucun modérateur en file d'attente");
            return [
                'processed_assignments' => 0,
                'remaining_in_queue' => 0
            ];
        }

        $assignmentCount = 0;
        $assignmentService = new ModeratorAssignmentService();

        // Traiter chaque modérateur en file d'attente
        foreach ($queuedModerators as $queue) {
            if (empty($availableProfiles)) {
                break; // Plus de profils disponibles
            }

            $moderator = $queue->moderator;

            // Vérifier si le modérateur est toujours actif et n'a pas déjà été assigné
            if (
                !$moderator ||
                $moderator->status !== 'active' ||
                !$moderator->is_online ||
                in_array($moderator->id, $assignedModerators)
            ) {

                if (!$moderator || $moderator->status !== 'active' || !$moderator->is_online) {
                    $this->removeFromQueue($queue->moderator_id);
                    Log::info("Modérateur retiré de la file d'attente (inactif)", [
                        'moderator_id' => $queue->moderator_id
                    ]);
                } else {
                    Log::info("Modérateur ignoré (déjà assigné dans ce cycle)", [
                        'moderator_id' => $moderator->id
                    ]);
                }
                continue;
            }

            // Sélectionner le profil prioritaire (avec messages en attente)
            $profileId = null;
            foreach ($availableProfiles as $key => $availableProfileId) {
                if (in_array($availableProfileId, $profilesWithPendingMessages)) {
                    $profileId = $availableProfileId;
                    unset($availableProfiles[$key]);
                    $availableProfiles = array_values($availableProfiles); // Réindexer
                    break;
                }
            }

            // Si aucun profil prioritaire, prendre le premier disponible
            if (!$profileId && !empty($availableProfiles)) {
                $profileId = array_shift($availableProfiles);
            }

            if (!$profileId) {
                break; // Plus de profils disponibles
            }

            // Attribuer le profil au modérateur
            $assignment = $assignmentService->assignProfileToModerator(
                $moderator->id,
                $profileId,
                true // Définir comme profil principal
            );

            if ($assignment) {
                // Retirer le modérateur de la file d'attente APRÈS attribution réussie
                $this->removeFromQueue($moderator->id);
                $assignmentCount++;
                $assignedModerators[] = $moderator->id; // Ajouter à la liste des assignés

                Log::info("Profil attribué depuis la file d'attente", [
                    'moderator_id' => $moderator->id,
                    'profile_id' => $profileId,
                    'profils_restants' => count($availableProfiles)
                ]);

                event(new \App\Events\ProfileAssigned(
                    $moderator,
                    $profileId,
                    $assignment->id,
                    null,
                    'queue'
                ));
            }
        }

        // Ajouter les modérateurs inactifs à la file d'attente
        $this->addIdleModeratorsToQueue($assignedModerators);

        return [
            'processed_assignments' => $assignmentCount,
            'remaining_in_queue' => ModeratorQueue::count()
        ];
    }


    /**
     * Ajouter les modérateurs inactifs à la file d'attente
     */
    private function addIdleModeratorsToQueue($assignedModerators = [])
    {
        // Trouver les modérateurs actifs sans assignation active et pas déjà assignés dans ce cycle
        $idleModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->whereNotIn('id', $assignedModerators)
            ->whereDoesntHave('moderatorProfileAssignments', function ($query) {
                $query->where('is_active', true);
            })
            ->whereNotIn('id', function ($query) {
                $query->select('moderator_id')
                    ->from('moderator_queues');
            })
            ->get();

        foreach ($idleModerators as $moderator) {
            $this->addToQueue($moderator->id);
            Log::info("Modérateur ajouté à la file d'attente", [
                'moderator_id' => $moderator->id
            ]);
        }

        Log::info("Modérateurs inactifs ajoutés à la file d'attente", [
            'count' => $idleModerators->count(),
            'excluded_assigned' => count($assignedModerators)
        ]);
    }

    /**
     * Réorganiser la file d'attente selon les priorités
     */
    public function reorderQueue()
    {
        // Récupérer tous les modérateurs en file d'attente
        $queues = ModeratorQueue::orderBy('priority', 'desc')
            ->orderBy('queued_at', 'asc')
            ->get();

        if ($queues->isEmpty()) {
            return 0;
        }

        // Réaffecter les positions
        $position = 1;
        foreach ($queues as $queue) {
            if ($queue->position != $position) {
                $queue->position = $position;
                $queue->save();

                // Mettre à jour la position dans l'assignation
                ModeratorProfileAssignment::where('user_id', $queue->moderator_id)
                    ->update(['queue_position' => $position]);

                // Notifier le modérateur du changement
                $this->notifyQueuePositionChanged($queue->moderator_id);
            }

            $position++;
        }

        return count($queues);
    }

    /**
     * Obtenir l'état actuel de la file d'attente
     */
    public function getQueueStatus()
    {
        $queues = ModeratorQueue::orderBy('position')
            ->with('moderator')
            ->get()
            ->map(function ($queue) {
                return [
                    'moderator_id' => $queue->moderator_id,
                    'moderator_name' => $queue->moderator->name,
                    'position' => $queue->position,
                    'queued_at' => $queue->queued_at,
                    'priority' => $queue->priority,
                    'estimated_wait_time' => $queue->estimated_wait_time
                ];
            });

        return [
            'queue_length' => count($queues),
            'queued_moderators' => $queues,
            'available_profiles' => count($this->getAvailableProfiles())
        ];
    }

    /**
     * Obtenir le prochain modérateur en attente
     */
    public function getNextModerator()
    {
        $queue = ModeratorQueue::orderBy('position')
            ->first();

        if (!$queue) {
            return null;
        }

        return User::find($queue->moderator_id);
    }

    /**
     * Obtenir la dernière position dans la file d'attente
     */
    private function getLastPosition()
    {
        $lastQueue = ModeratorQueue::orderBy('position', 'desc')->first();

        if (!$lastQueue) {
            return 0;
        }

        return $lastQueue->position;
    }

    /**
     * Calculer le temps d'attente estimé
     */
    private function calculateEstimatedWaitTime()
    {
        // Estimer en fonction du nombre de profils disponibles et de la longueur de la file
        $queueLength = ModeratorQueue::count();
        $availableProfiles = count($this->getAvailableProfiles());

        // Formule simple: 5 minutes par position dans la file divisé par le nombre de profils disponibles
        $waitMinutes = 5 * ($queueLength + 1) / max(1, $availableProfiles);

        return max(1, min(30, $waitMinutes)); // Entre 1 et 30 minutes
    }

    /**
     * Obtenir les profils disponibles pour attribution - VERSION SIMPLIFIÉE
     */
    private function getAvailableProfiles()
    {
        // Récupérer tous les profils actifs
        $allProfiles = Profile::where('status', 'active')->pluck('id')->toArray();

        // Récupérer les profils avec des messages en attente (PRIORITÉ ABSOLUE)
        // Utiliser la même logique que ModeratorAssignmentService pour identifier les messages en attente
        // Étape 1: Obtenir le dernier message de chaque conversation (client-profil)
        $latestMessagesSubquery = \Illuminate\Support\Facades\DB::table('messages')
            ->select(
                'client_id',
                'profile_id',
                \Illuminate\Support\Facades\DB::raw('MAX(created_at) as latest_message_at')
            )
            ->groupBy('client_id', 'profile_id');

        // Étape 2: Récupérer les conversations où le dernier message vient du client
        $profilesWithPendingMessages = \Illuminate\Support\Facades\DB::table('messages as m')
            ->joinSub($latestMessagesSubquery, 'latest', function ($join) {
                $join->on('m.client_id', '=', 'latest.client_id')
                    ->on('m.profile_id', '=', 'latest.profile_id')
                    ->on('m.created_at', '=', 'latest.latest_message_at');
            })
            ->where('m.is_from_client', true)
            ->select('m.profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils déjà assignés comme profil principal
        $assignedProfiles = ModeratorProfileAssignment::where('is_active', true)
            ->where('is_primary', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils verrouillés (NON EXPIRÉS)
        $lockedProfiles = ProfileLock::where('expires_at', '>', now())
            ->whereNull('deleted_at')
            ->pluck('profile_id')
            ->toArray();

        $availableProfiles = [];

        // RÈGLE 1 : Tous les profils avec messages en attente sont TOUJOURS disponibles
        foreach ($profilesWithPendingMessages as $profileId) {
            if (in_array($profileId, $allProfiles)) {
                $availableProfiles[] = $profileId;

                // Déverrouiller forcément les profils avec messages en attente
                if (in_array($profileId, $lockedProfiles)) {
                    $lockService = new ProfileLockService();
                    $lockService->unlockProfile($profileId);
                    Log::info("Profil déverrouillé automatiquement (message en attente)", [
                        'profile_id' => $profileId
                    ]);
                }
            }
        }

        // RÈGLE 2 : Ajouter les profils non assignés et non verrouillés
        foreach ($allProfiles as $profileId) {
            if (
                !in_array($profileId, $availableProfiles) &&
                !in_array($profileId, $assignedProfiles) &&
                !in_array($profileId, $lockedProfiles)
            ) {
                $availableProfiles[] = $profileId;
            }
        }

        Log::info("Profils disponibles calculés", [
            'total_profils' => count($allProfiles),
            'profils_avec_messages' => count($profilesWithPendingMessages),
            'profils_assignés' => count($assignedProfiles),
            'profils_verrouillés' => count($lockedProfiles),
            'profils_disponibles' => count($availableProfiles)
        ]);

        return array_unique($availableProfiles);
    }


    /**
     * Notifier un modérateur du changement de position
     */
    private function notifyQueuePositionChanged($moderatorId)
    {
        $queue = ModeratorQueue::where('moderator_id', $moderatorId)->first();

        if (!$queue) {
            return false;
        }

        // Récupérer les profils disponibles
        $availableProfiles = $this->getAvailableProfiles();

        // Émettre l'événement
        event(new ModeratorQueuePositionChanged(
            $moderatorId,
            $queue->position,
            $queue->estimated_wait_time,
            $availableProfiles
        ));

        return true;
    }

    /**
     * Notifier tous les modérateurs des changements de position
     */
    private function notifyAllQueueChanges()
    {
        $queues = ModeratorQueue::orderBy('position')->get();

        foreach ($queues as $queue) {
            $this->notifyQueuePositionChanged($queue->moderator_id);
        }

        return count($queues);
    }



    public function checkAndProcessQueue($assignedModerators = [])
    {
        Log::info("🔍 Vérification et traitement de la file d'attente", [
            'assigned_moderators_count' => count($assignedModerators)
        ]);

        // Nettoyer la file d'attente des modérateurs inactifs
        $this->cleanupQueue();

        // Traiter la file d'attente avec les modérateurs déjà assignés
        $results = $this->processQueue($assignedModerators);

        Log::info("📋 Résultats du traitement de la file d'attente", [
            'processed_assignments' => $results['processed_assignments'],
            'remaining_in_queue' => $results['remaining_in_queue']
        ]);

        return $results;
    }

    /**
     * Nettoie la file d'attente en supprimant les entrées pour les modérateurs inactifs ou hors ligne
     * 
     * @return int Le nombre d'entrées supprimées
     */
    private function cleanupQueue()
    {
        // Trouver les modérateurs en file d'attente qui sont inactifs ou hors ligne
        $inactiveQueueEntries = ModeratorQueue::whereHas('moderator', function ($query) {
            $query->where('status', '!=', 'active')
                ->orWhere('is_online', false);
        })->get();

        $removedCount = 0;

        foreach ($inactiveQueueEntries as $entry) {
            $moderatorId = $entry->moderator_id;
            $this->removeFromQueue($moderatorId);
            $removedCount++;

            Log::info("Modérateur inactif retiré de la file d'attente", [
                'moderator_id' => $moderatorId,
                'reason' => 'inactive_or_offline'
            ]);
        }

        // Vérifier également les entrées orphelines (sans modérateur associé)
        $orphanEntries = ModeratorQueue::whereDoesntHave('moderator')->get();

        foreach ($orphanEntries as $entry) {
            $entry->delete();
            $removedCount++;

            Log::info("Entrée orpheline supprimée de la file d'attente", [
                'queue_id' => $entry->id,
                'moderator_id' => $entry->moderator_id,
                'reason' => 'orphan_entry'
            ]);
        }

        if ($removedCount > 0) {
            // Réorganiser la file d'attente après les suppressions
            $this->reorderQueue();
        }

        Log::info("Nettoyage de la file d'attente terminé", [
            'removed_entries' => $removedCount
        ]);

        return $removedCount;
    }
}
