<?php

namespace App\Services;

use App\Events\ProfileAssigned;
use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Events\ClientAssigned;
use Illuminate\Support\Facades\Log;

class ModeratorAssignmentService
{
    /**
     * Attribuer un profil à un modérateur
     *
     * @param User $moderator Le modérateur
     * @param Profile|null $profile Le profil à attribuer (ou null pour auto-sélection)
     * @param bool $makePrimary Whether to make this the primary profile
     * @return ModeratorProfileAssignment|null
     */
    public function assignProfileToModerator(User $moderator, ?Profile $profile = null, $makePrimary = true): ?ModeratorProfileAssignment
    {
        // Vérifier que l'utilisateur est bien un modérateur
        if (!$moderator->isModerator()) {
            return null;
        }

        // Si aucun profil spécifique n'est fourni, en trouver un disponible
        if (!$profile) {
            $profile = $this->findAvailableProfile();

            // Si aucun profil n'est disponible, retourner null
            if (!$profile) {
                return null;
            }
        } else {
            // Vérifier si le profil est déjà utilisé activement par un autre modérateur
            $isProfileInUse = ModeratorProfileAssignment::where('profile_id', $profile->id)
                ->where('is_active', true)
                ->exists();

            if ($isProfileInUse) {
                return null;
            }
        }

        try {
            // Utiliser une transaction pour garantir l'intégrité des données
            return DB::transaction(function () use ($moderator, $profile, $makePrimary) {
                Log::info("[DEBUG] Début de l'attribution de profil en transaction", [
                    'moderator_id' => $moderator->id,
                    'profile_id' => $profile->id,
                    'makePrimary' => $makePrimary ? 'OUI' : 'NON'
                ]);

                // 1. Si on crée un profil principal, désactiver d'abord tous les autres profils principaux
                if ($makePrimary) {
                    $primaryAssignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
                        ->where('is_primary', true)
                        ->get();

                    foreach ($primaryAssignments as $assignment) {
                        Log::info("[DEBUG] Désactivation du profil principal", [
                            'assignment_id' => $assignment->id,
                            'profile_id' => $assignment->profile_id
                        ]);
                        $assignment->is_primary = false;
                        $assignment->save();
                    }
                }

                // 2. Désactiver les profils actifs un par un
                $activeAssignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
                    ->where('is_active', true)
                    ->get();

                foreach ($activeAssignments as $assignment) {
                    Log::info("[DEBUG] Désactivation du profil actif", [
                        'assignment_id' => $assignment->id,
                        'profile_id' => $assignment->profile_id
                    ]);
                    $assignment->is_active = false;
                    $assignment->save();
                }

                // 3. Vérifier s'il existe déjà une attribution pour ce profil
                $existingAssignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
                    ->where('profile_id', $profile->id)
                    ->first();

                if ($existingAssignment) {
                    Log::info("[DEBUG] Mise à jour d'une attribution existante", [
                        'assignment_id' => $existingAssignment->id
                    ]);
                    $existingAssignment->is_active = true;
                    $existingAssignment->is_primary = $makePrimary;
                    $existingAssignment->last_activity = now();
                    $existingAssignment->save();

                    $assignment = $existingAssignment;
                } else {
                    // 4. Créer la nouvelle attribution
                    Log::info("[DEBUG] Création d'une nouvelle attribution");
                    $assignment = ModeratorProfileAssignment::create([
                        'user_id' => $moderator->id,
                        'profile_id' => $profile->id,
                        'is_active' => true,
                        'is_primary' => $makePrimary,
                        'last_activity' => now(),
                    ]);
                }

                // Déclencher l'événement d'attribution avec les informations complètes
                event(new ProfileAssigned($moderator, $profile, $makePrimary, $clientId ?? null));

                Log::info("[DEBUG] Attribution de profil terminée avec succès", [
                    'assignment_id' => $assignment->id
                ]);

                return $assignment;
            });
        } catch (\Exception $e) {
            Log::error("[DEBUG] Erreur lors de l'attribution du profil", [
                'moderator_id' => $moderator->id,
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Trouver un profil disponible pour attribution
     *
     * @return Profile|null
     */
    private function findAvailableProfile(): ?Profile
    {
        // Récupérer les ID des profils actuellement attribués et actifs
        $assignedProfileIds = ModeratorProfileAssignment::where('is_active', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer un profil actif qui n'est pas actuellement attribué
        return Profile::where('status', 'active')
            ->whereNotIn('id', $assignedProfileIds)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Libérer un profil attribué à un modérateur
     *
     * @param User $moderator Le modérateur
     * @return bool
     */
    public function releaseProfile(User $moderator): bool
    {
        // Récupérer les assignations actives
        $assignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->get();

        // Aucune assignation à traiter
        if ($assignments->isEmpty()) {
            return true;
        }

        // Désactiver d'abord les profils principaux pour éviter la violation de contrainte
        foreach ($assignments as $assignment) {
            if ($assignment->is_primary) {
                $assignment->is_primary = false;
                $assignment->save();
            }
        }

        // Ensuite désactiver tous les profils
        foreach ($assignments as $assignment) {
            $assignment->is_active = false;
            $assignment->save();
        }

        return true;
    }

    /**
     * Obtenir le profil actuellement attribué à un modérateur
     *
     * @param User $moderator Le modérateur
     * @return Profile|null
     */
    public function getCurrentAssignedProfile(User $moderator): ?Profile
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->first();

        return $assignment ? $assignment->profile : null;
    }

    /**
     * Mettre à jour la dernière activité d'un modérateur sur un profil
     *
     * @param User $moderator Le modérateur
     * @param int|null $profileId Specific profile ID to update (optional)
     * @return bool
     */
    public function updateLastActivity(User $moderator, $profileId = null): bool
    {
        try {
            $query = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true);

            if ($profileId) {
                $query->where('profile_id', $profileId);
            }

            // Récupérer les assignations individuellement au lieu de faire un update massif
            $assignments = $query->get();

            foreach ($assignments as $assignment) {
                $assignment->last_activity = now();
                $assignment->save();
            }

            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur lors de la mise à jour de l\'activité', [
                'moderator_id' => $moderator->id,
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Réattribuer les profils des modérateurs inactifs
     *
     * @param int $inactiveMinutes Nombre de minutes d'inactivité avant réattribution
     * @return int Nombre de profils réattribués
     */
    public function reassignInactiveProfiles(int $inactiveMinutes = 30): int
    {
        $cutoffTime = now()->subMinutes($inactiveMinutes);

        // Récupérer les attributions inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where('last_activity', '<', $cutoffTime)
            ->get();

        // Compter combien d'attributions vont être désactivées
        $count = $inactiveAssignments->count();

        // Traiter les attributions une par une
        foreach ($inactiveAssignments as $assignment) {
            // On modifie d'abord is_primary à false si nécessaire
            if ($assignment->is_primary) {
                $assignment->is_primary = false;
                $assignment->save();
            }

            // Ensuite, dans une opération séparée, on désactive l'attribution
            $assignment->is_active = false;
            $assignment->save();
        }

        return $count;
    }

    /**
     * Get all active profile assignments for a moderator
     * 
     * @param User $moderator The moderator
     * @return \Illuminate\Database\Eloquent\Collection The collection of active assignments
     */
    public function getAllAssignedProfiles(User $moderator)
    {
        return ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->with('profile')
            ->get()
            ->pluck('profile');
    }

    /**
     * Find a moderator with the least workload to handle a new client message
     * 
     * @param int $clientId The client ID
     * @param int $profileId The profile ID
     * @return User|null The selected moderator or null if none available
     */
    public function findLeastBusyModerator($clientId, $profileId)
    {
        // Get online moderators (active in the last 30 minutes) - Augmenté de 10 à 30 minutes
        $onlineModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->get(); // Récupérer tous les modérateurs actifs, sans condition d'activité récente

        // Log pour le débogage
        Log::info("[DEBUG] Recherche de modérateurs", [
            'client_id' => $clientId,
            'profile_id' => $profileId,
            'moderateurs_trouvés' => $onlineModerators->count(),
            'moderateurs' => $onlineModerators->pluck('id', 'name')->toArray()
        ]);

        if ($onlineModerators->isEmpty()) {
            Log::warning("[DEBUG] Aucun modérateur en ligne trouvé");
            return null;
        }

        // Calculate workload for each moderator (number of active conversations)
        $workloads = [];
        foreach ($onlineModerators as $moderator) {
            // Count unique clients this moderator is currently handling
            $activeClientCount = DB::table('messages')
                ->join('moderator_profile_assignments', function ($join) use ($moderator) {
                    $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                        ->where('moderator_profile_assignments.user_id', '=', $moderator->id)
                        ->where('moderator_profile_assignments.is_active', '=', true);
                })
                ->where('messages.created_at', '>', now()->subMinutes(30))
                ->distinct('messages.client_id')
                ->count('messages.client_id');

            $workloads[$moderator->id] = $activeClientCount;
        }

        // Log les charges de travail
        Log::info("[DEBUG] Charges de travail des modérateurs", [
            'workloads' => $workloads
        ]);

        // Find moderators who already have this profile assigned
        $moderatorsWithProfile = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->toArray();

        // Log les modérateurs avec le profil déjà assigné
        Log::info("[DEBUG] Modérateurs avec le profil déjà assigné", [
            'profile_id' => $profileId,
            'moderateurs' => $moderatorsWithProfile
        ]);

        // Prioritize:
        // 1. Moderators who already have the profile assigned
        // 2. Moderators with the least workload
        // 3. Moderators who have no conversations

        $selectedModeratorId = null;
        $lowestWorkload = PHP_INT_MAX;

        // First check if any moderator with this profile has no workload
        foreach ($moderatorsWithProfile as $modId) {
            if (isset($workloads[$modId]) && $workloads[$modId] == 0) {
                Log::info("[DEBUG] Modérateur sélectionné (avec profil, sans charge)", [
                    'moderator_id' => $modId
                ]);
                return User::find($modId);
            }
        }

        // Then find moderator with this profile with least workload
        foreach ($moderatorsWithProfile as $modId) {
            if (isset($workloads[$modId]) && $workloads[$modId] < $lowestWorkload) {
                $selectedModeratorId = $modId;
                $lowestWorkload = $workloads[$modId];
            }
        }

        // Si nous avons déjà sélectionné un modérateur avec le profil assigné
        if ($selectedModeratorId) {
            Log::info("[DEBUG] Modérateur sélectionné (avec profil, charge minimale)", [
                'moderator_id' => $selectedModeratorId,
                'workload' => $lowestWorkload
            ]);
            return User::find($selectedModeratorId);
        }

        // If no moderator has this profile or all are too busy, 
        // find any moderator with no workload
        foreach ($workloads as $modId => $workload) {
            if ($workload == 0) {
                Log::info("[DEBUG] Modérateur sélectionné (sans charge)", [
                    'moderator_id' => $modId
                ]);
                return User::find($modId);
            }
        }

        // If still no selection, pick the one with least workload
        $selectedModeratorId = null;
        $lowestWorkload = PHP_INT_MAX;

        foreach ($workloads as $modId => $workload) {
            if ($workload < $lowestWorkload) {
                $selectedModeratorId = $modId;
                $lowestWorkload = $workload;
            }
        }

        if ($selectedModeratorId) {
            Log::info("[DEBUG] Modérateur sélectionné (charge minimale générale)", [
                'moderator_id' => $selectedModeratorId,
                'workload' => $lowestWorkload
            ]);
            return User::find($selectedModeratorId);
        }

        Log::warning("[DEBUG] Aucun modérateur n'a pu être sélectionné");
        return null;
    }

    /**
     * Assign a client's conversation to a moderator
     * 
     * @param int $clientId The client ID
     * @param int $profileId The profile ID
     * @return User|null The assigned moderator or null if failed
     */
    public function assignClientToModerator($clientId, $profileId)
    {
        Log::info("[DEBUG] Début assignClientToModerator", [
            'client_id' => $clientId,
            'profile_id' => $profileId
        ]);

        // Find the most appropriate moderator based on load balancing
        $moderator = $this->findLeastBusyModerator($clientId, $profileId);

        if (!$moderator) {
            Log::warning("[DEBUG] Aucun modérateur trouvé pour l'attribution");
            return null;
        }

        Log::info("[DEBUG] Modérateur sélectionné pour attribution", [
            'moderator_id' => $moderator->id,
            'moderator_name' => $moderator->name
        ]);

        // Check if any moderator already has this profile assigned
        $currentAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        // If another moderator has this profile, we need to transfer it
        if ($currentAssignment && $currentAssignment->user_id !== $moderator->id) {
            Log::info("[DEBUG] Transfert du profil d'un autre modérateur", [
                'from_moderator' => $currentAssignment->user_id,
                'to_moderator' => $moderator->id
            ]);

            // Désactiver l'attribution actuelle
            $currentAssignment->is_active = false;
            $currentAssignment->save();
        }

        // Check if the moderator already has this profile assigned
        $hasProfile = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        if (!$hasProfile) {
            $profile = Profile::find($profileId);
            if (!$profile) {
                Log::error("[DEBUG] Profil introuvable", [
                    'profile_id' => $profileId
                ]);
                return null;
            }

            // Assign as a primary profile if the moderator has no other active profiles
            $hasPrimaryProfile = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->where('is_primary', true)
                ->exists();

            $assignment = $this->assignProfileToModerator($moderator, $profile, !$hasPrimaryProfile);

            if (!$assignment) {
                Log::error("[DEBUG] Échec de l'attribution du profil au modérateur");
                return null;
            }

            Log::info("[DEBUG] Profil attribué au modérateur", [
                'assignment_id' => $assignment->id,
                'is_primary' => $assignment->is_primary
            ]);
        }

        // Update last activity
        $this->updateLastActivity($moderator, $profileId);

        // Get client and profile details for the event
        $client = User::find($clientId);
        $profile = Profile::find($profileId);

        // Broadcast that this client has been assigned to the moderator
        event(new ClientAssigned($moderator, $client, $profile));

        Log::info("[DEBUG] Événement ClientAssigned émis");

        return $moderator;
    }

    /**
     * Get all clients who need responses, ordered by priority
     * 
     * @return \Illuminate\Support\Collection Collection of client IDs and profile IDs
     */
    public function getClientsNeedingResponse()
    {
        Log::info("[DEBUG] Recherche des clients nécessitant une réponse");

        // Trouve les derniers messages pour chaque paire client-profil
        $latestClientMessages = DB::table('messages as m1')
            ->select(
                'm1.client_id',
                'm1.profile_id',
                DB::raw('MAX(m1.id) as last_message_id'),
                DB::raw('MAX(m1.created_at) as last_message_time')
            )
            ->where('m1.is_from_client', true)
            ->where('m1.created_at', '>', now()->subHours(24)) // Limiter aux messages des dernières 24h
            ->groupBy('m1.client_id', 'm1.profile_id')
            ->get();

        Log::info("[DEBUG] Derniers messages clients trouvés", [
            'count' => $latestClientMessages->count()
        ]);

        // Pour chaque dernier message client, vérifier s'il a une réponse
        $clientsNeedingResponse = collect();

        foreach ($latestClientMessages as $clientMessage) {
            // Chercher si une réponse existe après ce message
            $hasResponse = DB::table('messages')
                ->where('client_id', $clientMessage->client_id)
                ->where('profile_id', $clientMessage->profile_id)
                ->where('is_from_client', false) // Message du modérateur/profil
                ->where('created_at', '>', $clientMessage->last_message_time)
                ->exists();

            // Si aucune réponse n'existe, ajouter à la liste
            if (!$hasResponse) {
                $clientsNeedingResponse->push([
                    'client_id' => $clientMessage->client_id,
                    'profile_id' => $clientMessage->profile_id,
                    'message_id' => $clientMessage->last_message_id,
                    'created_at' => $clientMessage->last_message_time,
                ]);
            }
        }

        Log::info("[DEBUG] Clients nécessitant une réponse identifiés", [
            'count' => $clientsNeedingResponse->count()
        ]);

        // Trier par ancienneté (les plus anciens en premier)
        return $clientsNeedingResponse->sortBy('created_at')->values();
    }

    /**
     * Process all unassigned client messages and assign them to moderators
     * 
     * @return int Number of clients assigned
     */
    public function processUnassignedMessages()
    {
        $clientsAssigned = 0;
        $clientsNeedingResponse = $this->getClientsNeedingResponse();

        foreach ($clientsNeedingResponse as $client) {
            $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

            if ($moderator) {
                $clientsAssigned++;
            }
        }

        return $clientsAssigned;
    }
}
