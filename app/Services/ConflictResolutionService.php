<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Services\ModeratorAssignmentService;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\ModeratorQueueService;

/**
 * Service pour gérer les conflits liés aux attributions des profils aux modérateurs.
 * 
 * Ce service s'assure que :
 * - lorsqu'il y a plusieurs modérateurs en concurrence pour peu de profils,
 *   il répartit les profils selon la priorité et l'activité récente des modérateurs,
 * - il détecte et corrige les erreurs comme un client attribué à plusieurs modérateurs,
 *   ou un modérateur avec plusieurs profils principaux simultanés,
 * - il maintient la cohérence des assignations pour éviter les doublons ou conflits,
 * - il logue toutes ces actions pour un suivi et une analyse éventuelle.
 * 
 * L'objectif est de garantir une distribution juste et efficace des profils,
 * tout en évitant les collisions ou incohérences dans la gestion des conversations.
 */
class ConflictResolutionService
{
    /**
     * Gère une collision de connexion entre plusieurs modérateurs
     * 
     * @param array $moderatorIds Les IDs des modérateurs impliqués
     * @param array $availableProfiles Les profils disponibles initialement
     * @return array Les allocations de profils aux modérateurs
     */
    public function handleConnectionCollision($moderatorIds, $availableProfiles)
    {
        // Récupérer TOUS les profils avec des messages en attente, pas seulement ceux fournis
        $allPendingProfiles = app(ModeratorAssignmentService::class)->getProfilesWithPendingMessages();

        // Fusionner avec les profils disponibles fournis
        $allAvailableProfiles = array_unique(array_merge($availableProfiles, $allPendingProfiles));

        Log::info("Gestion avancée d'une collision de connexion", [
            'moderator_ids' => $moderatorIds,
            'initial_available_profiles' => $availableProfiles,
            'all_pending_profiles' => $allPendingProfiles,
            'all_available_profiles' => $allAvailableProfiles,
            'timestamp' => now()->toDateTimeString()
        ]);

        // Si pas assez de profils pour tous les modérateurs, certains seront mis en file d'attente
        $allocations = [];
        $queueService = app(ModeratorQueueService::class);

        // Trier les modérateurs par priorité (par exemple, par ID le plus bas)
        sort($moderatorIds);

        // Pour chaque modérateur, essayer d'attribuer un profil disponible
        foreach ($moderatorIds as $moderatorId) {
            // Vérifier si ce modérateur a déjà un profil assigné
            $existingAssignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                // Le modérateur a déjà un profil, le conserver
                $allocations[$moderatorId] = $existingAssignment->profile_id;

                // Retirer ce profil de la liste des disponibles
                $allAvailableProfiles = array_diff($allAvailableProfiles, [$existingAssignment->profile_id]);
            } else if (!empty($allAvailableProfiles)) {
                // Attribuer un nouveau profil au modérateur
                $profileId = array_shift($allAvailableProfiles);
                $allocations[$moderatorId] = $profileId;

                // Créer l'assignation immédiatement
                $assignmentService = app(ModeratorAssignmentService::class);
                $newAssignment = $assignmentService->assignProfileToModerator($moderatorId, $profileId, true);

                if ($newAssignment) {
                    Log::info("Profil attribué lors de la résolution de collision", [
                        'moderator_id' => $moderatorId,
                        'profile_id' => $profileId,
                        'timestamp' => now()->toDateTimeString()
                    ]);

                    // Émettre l'événement pour notifier le frontend
                    event(new \App\Events\ProfileAssigned(
                        User::find($moderatorId),
                        $profileId,
                        $newAssignment->id,
                        null,
                        'collision_resolution'
                    ));
                }
            } else {
                // Plus de profils disponibles, ajouter le modérateur à la file d'attente

                $queueService->addToQueue($moderatorId);

                Log::info("Modérateur ajouté à la file d'attente (collision)", [
                    'moderator_id' => $moderatorId,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        }

        // Journaliser le résultat final
        Log::warning("Conflit d'attribution détecté", [
            'type' => 'connection_collision',
            'moderator_ids' => $moderatorIds,
            'available_profiles' => $availableProfiles,
            'all_available_profiles' => $allAvailableProfiles,
            'allocations' => $allocations,
            'timestamp' => now()->toDateTimeString()
        ]);

        return $allocations;
    }

    /**
     * Prioriser les modérateurs par horodatage
     */
    public function prioritizeByTimestamp($moderatorIds)
    {
        $moderatorsWithTimestamp = [];

        foreach ($moderatorIds as $moderatorId) {
            $lastActivity = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('is_active', true)
                ->orderBy('last_activity', 'desc')
                ->value('last_activity');

            $moderatorsWithTimestamp[$moderatorId] = $lastActivity ? $lastActivity : now()->subDay();
        }

        // Trier par activité la plus récente
        arsort($moderatorsWithTimestamp);

        return array_keys($moderatorsWithTimestamp);
    }

    /**
     * Prioriser par ID modérateur (fallback)
     */
    public function prioritizeById($moderatorIds)
    {
        sort($moderatorIds);
        return $moderatorIds;
    }

    /**
     * Logger les conflits pour monitoring
     */
    public function logConflict($conflictData)
    {
        Log::warning("Conflit d'attribution détecté", $conflictData);

        // On pourrait stocker ces informations dans une table dédiée
        // pour l'analyse des tendances

        return true;
    }

    /**
     * Valider l'intégrité des attributions
     */
    public function validateAssignmentIntegrity()
    {
        $issues = 0;

        // 1. Vérifier les clients attribués à plusieurs modérateurs pour le même profil
        $assignments = ModeratorProfileAssignment::where('is_active', true)->get();
        $clientProfileMap = [];

        foreach ($assignments as $assignment) {
            $clientIds = $assignment->conversation_ids ?? [];

            foreach ($clientIds as $clientId) {
                $key = $clientId . '-' . $assignment->profile_id;

                if (!isset($clientProfileMap[$key])) {
                    $clientProfileMap[$key] = [];
                }

                $clientProfileMap[$key][] = $assignment->user_id;
            }
        }

        // Traiter les clients attribués à plusieurs modérateurs
        foreach ($clientProfileMap as $key => $moderatorIds) {
            if (count($moderatorIds) > 1) {
                list($clientId, $profileId) = explode('-', $key);

                Log::warning("Client attribué à plusieurs modérateurs", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_ids' => $moderatorIds
                ]);

                // Conserver uniquement l'attribution au modérateur le plus actif
                $mostActiveModeratorId = $this->findMostActiveModerator($moderatorIds, $profileId);

                foreach ($moderatorIds as $moderatorId) {
                    if ($moderatorId != $mostActiveModeratorId) {
                        $this->removeClientFromModerator($clientId, $profileId, $moderatorId);
                        $issues++;
                    }
                }
            }
        }

        // 2. Vérifier les profils principaux multiples
        $usersWithMultiplePrimaryProfiles = User::where('type', 'moderateur')
            ->whereHas('moderatorProfileAssignments', function ($query) {
                $query->where('is_active', true)
                    ->where('is_primary', true);
            }, '>', 1)
            ->get();

        foreach ($usersWithMultiplePrimaryProfiles as $user) {
            Log::warning("Modérateur avec plusieurs profils principaux", [
                'moderator_id' => $user->id,
                'moderator_name' => $user->name
            ]);

            // Conserver uniquement le profil principal le plus récemment attribué
            $assignments = ModeratorProfileAssignment::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('is_primary', true)
                ->orderBy('assigned_at', 'desc')
                ->get();

            $keptFirst = false;
            foreach ($assignments as $assignment) {
                if (!$keptFirst) {
                    $keptFirst = true;
                    continue;
                }

                $assignment->is_primary = false;
                $assignment->save();
                $issues++;
            }
        }

        return $issues;
    }

    /**
     * Trouver le modérateur le plus actif pour un profil
     */
    private function findMostActiveModerator($moderatorIds, $profileId)
    {
        $assignments = ModeratorProfileAssignment::whereIn('user_id', $moderatorIds)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->get();

        $mostRecentActivity = null;
        $mostActiveModerator = null;

        foreach ($assignments as $assignment) {
            if (
                !$mostRecentActivity ||
                ($assignment->last_activity && $assignment->last_activity->isAfter($mostRecentActivity))
            ) {
                $mostRecentActivity = $assignment->last_activity;
                $mostActiveModerator = $assignment->user_id;
            }
        }

        return $mostActiveModerator ?: $moderatorIds[0];
    }

    /**
     * Supprimer un client de l'attribution d'un modérateur
     */
    private function removeClientFromModerator($clientId, $profileId, $moderatorId)
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            $assignment->removeConversation($clientId);
        }

        return true;
    }
}
