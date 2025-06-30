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

    /**
     * Traiter la file d'attente pour attribution
     */
    // Dans app/Services/ModeratorQueueService.php, modifier la méthode processQueue():

    public function processQueue()
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
            return 0; // Pas de profils disponibles
        }

        // Récupérer les modérateurs en attente
        $queuedModerators = ModeratorQueue::orderBy('position')
            ->with('moderator')
            ->get();

        if ($queuedModerators->isEmpty()) {
            Log::info("Aucun modérateur en file d'attente");
            return 0; // Pas de modérateurs en attente
        }

        $assignmentCount = 0;
        $assignmentService = new ModeratorAssignmentService();

        // Attribuer les profils disponibles aux modérateurs en attente
        foreach ($queuedModerators as $queue) {
            if (empty($availableProfiles)) {
                break;
            }

            $moderator = $queue->moderator;

            // Vérifier si le modérateur est toujours actif
            if (!$moderator || $moderator->status !== 'active') {
                $this->removeFromQueue($queue->moderator_id);
                continue;
            }

            // Sélectionner le profil prioritaire (avec messages en attente)
            $profileId = null;
            foreach ($availableProfiles as $key => $availableProfileId) {
                if (in_array($availableProfileId, $profilesWithPendingMessages)) {
                    $profileId = $availableProfileId;
                    unset($availableProfiles[$key]);
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
                // Retirer le modérateur de la file d'attente
                $this->removeFromQueue($moderator->id);
                $assignmentCount++;

                Log::info("Profil attribué depuis la file d'attente", [
                    'moderator_id' => $moderator->id,
                    'profile_id' => $profileId
                ]);
            }
        }

        return $assignmentCount;
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
     * Obtenir les profils disponibles pour attribution
     */
    private function getAvailableProfiles()
    {
        // Récupérer tous les profils actifs
        $allProfiles = Profile::where('status', 'active')->pluck('id')->toArray();

        // Récupérer les profils déjà assignés comme profil principal
        $assignedProfiles = ModeratorProfileAssignment::where('is_active', true)
            ->where('is_primary', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils verrouillés
        $lockedProfiles = ProfileLock::where('expires_at', '>', now())
            ->whereNull('deleted_at')
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils avec des messages en attente
        $profilesWithPendingMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->select('profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        // Combiner les profils non disponibles (seulement ceux qui n'ont PAS de messages en attente)
        $unavailableProfiles = [];
        foreach (array_merge($assignedProfiles, $lockedProfiles) as $profileId) {
            // Un profil n'est indisponible que s'il n'a pas de messages en attente
            if (!in_array($profileId, $profilesWithPendingMessages)) {
                $unavailableProfiles[] = $profileId;
            }
        }

        // Filtrer les profils disponibles
        $availableProfiles = array_diff($allProfiles, $unavailableProfiles);

        // S'assurer que tous les profils avec des messages en attente sont inclus
        foreach ($profilesWithPendingMessages as $profileId) {
            if (!in_array($profileId, $availableProfiles)) {
                $availableProfiles[] = $profileId;
            }
        }

        return array_values($availableProfiles);
    }

    /* private function getAvailableProfiles()
    {
        // Récupérer tous les profils actifs
        $allProfiles = Profile::where('status', 'active')->pluck('id')->toArray();

        // Récupérer les profils déjà assignés comme profil principal
        $assignedProfiles = ModeratorProfileAssignment::where('is_active', true)
            ->where('is_primary', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils verrouillés
        $lockedProfiles = ProfileLock::where('expires_at', '>', now())
            ->whereNull('deleted_at')
            ->pluck('profile_id')
            ->toArray();

        // Combiner les profils non disponibles
        $unavailableProfiles = array_unique(array_merge($assignedProfiles, $lockedProfiles));

        // Filtrer les profils disponibles
        $availableProfiles = array_diff($allProfiles, $unavailableProfiles);

        // Ajouter les profils avec des messages en attente, même s'ils sont assignés
        // car ils peuvent être partagés entre modérateurs
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

        return array_values($availableProfiles);
    } */

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
}
