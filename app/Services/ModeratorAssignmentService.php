<?php

namespace App\Services;

use App\Events\ProfileAssigned;
use App\Events\ClientAssigned;
use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Message;
use App\Services\ProfileLockService;
use App\Services\ModeratorQueueService;
use App\Services\ConflictResolutionService;
use App\Services\LoadBalancingService;

/**
 * Service pour gérer l'assignation des profils (clients) aux modérateurs
 * 
 * Ce service utilise plusieurs mécanismes pour :
 * - verrouiller les profils afin d'éviter que plusieurs modérateurs travaillent sur le même client en même temps,
 * - gérer une file d'attente des modérateurs disponibles,
 * - résoudre les conflits en cas de demandes concurrentes,
 * - équilibrer la charge pour répartir les clients entre modérateurs.
 * 
 * Le constructeur initialise les services nécessaires pour ces fonctions.
 */
class ModeratorAssignmentService
{
    protected $profileLockService;
    protected $queueService;
    protected $conflictService;
    protected $loadBalancingService;

    public function __construct(
        ?ProfileLockService $profileLockService = null,
        ?ModeratorQueueService $queueService = null,
        ?ConflictResolutionService $conflictService = null,
        ?LoadBalancingService $loadBalancingService = null
    ) {
        $this->profileLockService = $profileLockService ?? new ProfileLockService();
        $this->queueService = $queueService ?? new ModeratorQueueService();
        $this->conflictService = $conflictService ?? new ConflictResolutionService();
        $this->loadBalancingService = $loadBalancingService ?? new LoadBalancingService();
    }

    /**
     * Assigner un profil à un modérateur avec intégration du système de file d'attente et de verrous
     */
    public function assignProfileToModerator($moderatorId, $profileId = null, $isPrimary = false)
    {
        // Si aucun profil spécifié, trouver le profil le plus urgent
        if (!$profileId) {
            $profileId = $this->findMostUrgentProfile();
        }

        // Vérifier si le profil existe
        $profile = Profile::find($profileId);
        if (!$profile) {
            Log::warning("Profil non trouvé lors de l'assignation", [
                'profile_id' => $profileId
            ]);
            return null;
        }

        // Vérifier si le modérateur existe
        $moderator = User::find($moderatorId);
        if (!$moderator || $moderator->type !== 'moderateur') {
            Log::warning("Modérateur non trouvé ou invalide lors de l'assignation", [
                'moderator_id' => $moderatorId
            ]);
            return null;
        }

        // Verrouiller le profil pendant l'assignation pour éviter les conflits
        if (!$this->lockProfileForAssignment($profileId)) {
            // Si le profil est verrouillé, ajouter le modérateur à la file d'attente
            $this->addModeratorToQueue($moderatorId);
            Log::info("Le profil est verrouillé, modérateur ajouté à la file d'attente", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'queue_position' => $this->queueService->getQueuePosition($moderatorId)
            ]);
            return null;
        }

        try {
            // Vérifier si ce modérateur a déjà ce profil assigné
            $existingAssignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $profileId)
                ->first();

            // Vérifier combien de clients différents ont des messages en attente pour ce profil
            $clientsWithPendingMessages = Message::where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->distinct('client_id')
                ->pluck('client_id')
                ->toArray();

            $pendingClientsCount = count($clientsWithPendingMessages);

            // Vérifier si d'autres modérateurs ont déjà ce profil assigné
            $otherActiveAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->where('user_id', '!=', $moderatorId)
                ->get();

            // Gérer les connexions simultanées si nécessaire
            if ($otherActiveAssignments->isNotEmpty()) {
                $moderatorIds = $otherActiveAssignments->pluck('user_id')->push($moderatorId)->toArray();
                $this->handleSimultaneousConnections($moderatorIds, [$profileId]);
            }

            // Si un seul client a des messages en attente et le profil est déjà assigné à un autre modérateur
            if ($pendingClientsCount <= 1 && $otherActiveAssignments->isNotEmpty()) {
                // Valider pour s'assurer qu'il n'y a pas de double assignation
                if (!$this->validateUniqueClientProfileAssignment($clientsWithPendingMessages[0] ?? null, $profileId)) {
                    $this->unlockProfile($profileId);
                    return null;
                }
            }

            // Si le modérateur a déjà ce profil, mettre à jour l'assignation
            if ($existingAssignment) {
                $existingAssignment->is_active = true;
                $existingAssignment->last_activity = now();
                $existingAssignment->last_activity_check = now();

                if ($existingAssignment->queue_position) {
                    // Retirer de la file d'attente s'il était en attente
                    $this->removeModeratorFromQueue($moderatorId);
                    $existingAssignment->queue_position = null;
                }

                if ($isPrimary) {
                    // Désactiver tous les autres profils primaires
                    ModeratorProfileAssignment::where('user_id', $moderatorId)
                        ->where('is_primary', true)
                        ->where('id', '!=', $existingAssignment->id)
                        ->update(['is_primary' => false]);

                    $existingAssignment->is_primary = true;
                }

                $existingAssignment->save();
                $this->unlockProfile($profileId);

                // Déclencher l'événement d'assignation
                $this->triggerProfileAssignedEvent($moderator, $profile, $existingAssignment->id, $isPrimary);

                return $existingAssignment;
            }

            // Créer une nouvelle assignation
            $assignment = new ModeratorProfileAssignment([
                'user_id' => $moderatorId,
                'profile_id' => $profileId,
                'is_active' => true,
                'is_primary' => $isPrimary,
                'conversation_ids' => [],
                'active_conversations_count' => 0,
                'last_activity' => now(),
                'assigned_at' => now(),
                'last_activity_check' => now(),
                'queue_position' => null,
                'locked_clients' => []
            ]);

            $assignment->save();

            // Retirer de la file d'attente s'il était en attente
            $this->removeModeratorFromQueue($moderatorId);

            // Équilibrer la charge si nécessaire
            $this->rebalanceModeratorLoad();

            // Déclencher l'événement d'assignation
            $this->triggerProfileAssignedEvent($moderator, $profile, $assignment->id, $isPrimary);

            $this->unlockProfile($profileId);
            return $assignment;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'assignation du profil", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);
            $this->unlockProfile($profileId);
            return null;
        }
    }

    /**
     * Traiter les messages non assignés avec les nouveaux scénarios de priorité
     */
    public function processUnassignedMessages($urgentOnly = false)
    {
        $clientsAssigned = 0;
        $clientsNeedingResponse = $this->getClientsNeedingResponse($urgentOnly);

        Log::info("Traitement des messages non assignés", [
            'clients_count' => $clientsNeedingResponse->count(),
            'urgent_only' => $urgentOnly
        ]);

        // D'abord, libérer tous les profils qui ont reçu une réponse
        $onlineModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->get();

        foreach ($onlineModerators as $moderator) {
            $this->releaseRespondedProfiles($moderator);
        }

        // Nettoyer les verrous expirés avant traitement
        $this->profileLockService->cleanExpiredLocks();

        // Traiter d'abord les clients verrouillés - ils ont la priorité
        $lockedClients = $this->profileLockService->getAllLockedClients();
        foreach ($lockedClients as $clientId => $lockInfo) {
            if (isset($lockInfo['profile_id'])) {
                $clientIndex = $clientsNeedingResponse->search(function ($item) use ($clientId, $lockInfo) {
                    return $item['client_id'] == $clientId && $item['profile_id'] == $lockInfo['profile_id'];
                });

                if ($clientIndex !== false) {
                    $client = $clientsNeedingResponse[$clientIndex];
                    $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

                    if ($moderator) {
                        $clientsAssigned++;
                        $clientsNeedingResponse->forget($clientIndex);
                    }
                }
            }
        }

        // Traiter les clients restants par priorité
        foreach ($clientsNeedingResponse as $client) {
            // Assigner le client à un modérateur
            $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

            if ($moderator) {
                $clientsAssigned++;

                // Logger les messages urgents traités
                if ($client['is_urgent'] ?? false) {
                    Log::info("Message urgent assigné", [
                        'client_id' => $client['client_id'],
                        'profile_id' => $client['profile_id'],
                        'message_age' => Carbon::parse($client['created_at'])->diffForHumans(),
                        'assigned_to' => $moderator->id
                    ]);
                }
            } else {
                Log::warning("Échec de l'assignation du client", [
                    'client_id' => $client['client_id'],
                    'profile_id' => $client['profile_id']
                ]);
            }
        }

        // Résoudre les conflits restants
        $this->resolveAttributionConflicts();

        return $clientsAssigned;
    }

    /**
     * Trouver le modérateur le moins occupé avec intégration de la file d'attente, des verrous, et exclusion optionnelle
     *
     * @param int|null $clientId ID du client (optionnel)
     * @param int $profileId ID du profil
     * @param int|null $excludeModeratorId ID du modérateur à exclure (optionnel)
     * @return User|null Le modérateur trouvé ou null si aucun n'est disponible
     */
    public function findLeastBusyModerator($clientId, $profileId, $excludeModeratorId = null)
    {
        // 1. Vérification du verrou existant pour ce client sur ce profil
        if ($clientId && $this->profileLockService->isClientLocked($clientId, $profileId)) {
            $lockInfo = $this->profileLockService->getLockInfo($clientId, 'client');
            if (isset($lockInfo['moderator_id']) && (!$excludeModeratorId || $lockInfo['moderator_id'] != $excludeModeratorId)) {
                return User::find($lockInfo['moderator_id']);
            }
        }

        // 2. Récupération des modérateurs en ligne, avec exclusion si nécessaire
        $onlineModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->when($excludeModeratorId, fn($q) => $q->where('id', '!=', $excludeModeratorId))
            ->get();

        if ($onlineModerators->isEmpty()) {
            return null;
        }

        // 3. Vérification des modérateurs en file d'attente
        $nextQueuedModerator = $this->getNextModeratorFromQueue();
        if ($nextQueuedModerator && (!$excludeModeratorId || $nextQueuedModerator->id != $excludeModeratorId)) {
            Log::info("Modérateur en attente sélectionné", [
                'moderator_id' => $nextQueuedModerator->id,
                'queue_position' => $this->queueService->getQueuePosition($nextQueuedModerator->id)
            ]);
            return $nextQueuedModerator;
        }

        // 4. Calcul des scores de disponibilité
        $moderatorScores = [];
        foreach ($onlineModerators as $moderator) {
            $activeProfileIds = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->pluck('profile_id')
                ->toArray();

            $activeConversations = DB::table('messages')
                ->select(DB::raw('COUNT(DISTINCT CONCAT(client_id, "-", profile_id)) as count'))
                ->whereIn('profile_id', $activeProfileIds)
                ->where('created_at', '>', now()->subMinutes(10))
                ->first()->count ?? 0;

            $unansweredMessages = DB::table('messages as m1')
                ->join(DB::raw('(
                SELECT client_id, profile_id, MAX(created_at) as last_message_time
                FROM messages
                GROUP BY client_id, profile_id
            ) as m2'), function ($join) {
                    $join->on('m1.client_id', '=', 'm2.client_id')
                        ->on('m1.profile_id', '=', 'm2.profile_id')
                        ->on('m1.created_at', '=', 'm2.last_message_time');
                })
                ->whereIn('m1.profile_id', $activeProfileIds)
                ->where('m1.is_from_client', true)
                ->where('m1.created_at', '>', now()->subMinutes(10))
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('messages as m3')
                        ->whereRaw('m3.client_id = m1.client_id')
                        ->whereRaw('m3.profile_id = m1.profile_id')
                        ->where('m3.is_from_client', false)
                        ->whereRaw('m3.created_at > m1.created_at');
                })
                ->count();

            $score = max(0, 100 - ($activeConversations * 20) - ($unansweredMessages * 10));
            $status = $score > 50 ? 'disponible' : ($score >= 20 ? 'occupé' : 'surchargé');

            $moderatorScores[$moderator->id] = [
                'score' => $score,
                'moderator' => $moderator,
                'status' => $status
            ];
        }

        // 5. Garder le même modérateur si conditions valides
        $currentAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($currentAssignment) {
            $currentModeratorId = $currentAssignment->user_id;
            $currentModeratorScore = $moderatorScores[$currentModeratorId]['score'] ?? 0;

            $hasExistingConversation = DB::table('messages')
                ->where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->exists();

            if ($hasExistingConversation && $currentModeratorScore > 30 && (!$excludeModeratorId || $currentModeratorId != $excludeModeratorId)) {
                return User::find($currentModeratorId);
            }
        }

        // 6. Filtrer les modérateurs par disponibilité descendante
        $availableModerators = array_filter($moderatorScores, fn($item) => $item['status'] === 'disponible');
        if (empty($availableModerators)) {
            $availableModerators = array_filter($moderatorScores, fn($item) => $item['status'] === 'occupé');
        }
        if (empty($availableModerators)) {
            $availableModerators = $moderatorScores;
        }

        uasort($availableModerators, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestModerator = reset($availableModerators);

        return $bestModerator['moderator'] ?? null;
    }

    /**
     * Assigner un client à un modérateur avec contrôle de verrouillage
     */
    /* public function assignClientToModerator($clientId, $profileId)
    {
        // Vérifier si le client est déjà verrouillé
        if ($this->profileLockService->isClientLocked($clientId, $profileId)) {
            // Récupérer les informations du verrou
            $lockInfo = $this->profileLockService->getLockInfo($clientId, 'client');

            // Si le client est verrouillé par un modérateur, utiliser ce modérateur
            if ($lockInfo && isset($lockInfo['moderator_id'])) {
                $moderator = User::find($lockInfo['moderator_id']);
                if ($moderator && $moderator->type === 'moderateur') {
                    return $moderator;
                }
            }

            return null; // Client verrouillé mais pas par un modérateur valide
        }

        // Trouver le modérateur le moins occupé qui a déjà ce profil assigné
        $moderator = $this->findLeastBusyModerator($clientId, $profileId);

        // Si aucun modérateur n'est trouvé, chercher n'importe quel modérateur actif
        if (!$moderator) {
            $moderator = User::where('type', 'moderateur')
                ->where('status', 'active')
                ->whereDoesntHave('moderatorProfileAssignments', function ($query) {
                    $query->where('is_active', true)
                        ->where('is_primary', true);
                })
                ->first();

            // Si un modérateur sans profil principal est trouvé, lui assigner ce profil
            if ($moderator) {
                $this->assignProfileToModerator($moderator->id, $profileId, true);
            } else {
                // Chercher un modérateur avec une charge de travail faible
                $moderator = User::where('type', 'moderateur')
                    ->where('status', 'active')
                    ->whereHas('moderatorProfileAssignments', function ($query) {
                        $query->where('is_active', true)
                            ->where('active_conversations_count', '<', 3);
                    })
                    ->first();
            }
        }

        if (!$moderator) {
            Log::warning("Aucun modérateur disponible pour l'assignation du client", [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ]);
            return null; // Aucun modérateur disponible
        }

        // Verrouiller le client pour ce modérateur
        $this->profileLockService->lockClient($clientId, $profileId, $moderator->id, 30);

        // Ajouter le client aux conversations du modérateur
        $assignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            $success = $assignment->addConversation($clientId);

            if ($success) {
                Log::info("Client assigné avec succès au modérateur", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderator->id
                ]);

                // Déclencher l'événement d'assignation de client
                $this->triggerClientAssignedEvent($moderator, $clientId, $profileId);
            } else {
                Log::warning("Échec de l'ajout du client à la conversation", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderator->id
                ]);
            }
        } else {
            Log::warning("Aucune assignation trouvée pour ajouter le client", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => $moderator->id
            ]);
        }

        return $moderator;
    } */

    /**
     * Assigner un client à un modérateur avec contrôle de verrouillage
     */
    public function assignClientToModerator($clientId, $profileId)
    {
        // Vérifier si le client est déjà verrouillé
        if ($this->profileLockService->isClientLocked($clientId, $profileId)) {
            // Récupérer les informations du verrou
            $lockInfo = $this->profileLockService->getLockInfo($clientId, 'client');

            // Si le client est verrouillé par un modérateur, utiliser ce modérateur
            if ($lockInfo && isset($lockInfo['moderator_id'])) {
                $moderator = User::find($lockInfo['moderator_id']);
                if ($moderator && $moderator->type === 'moderateur') {
                    return $moderator;
                }
            }

            return null; // Client verrouillé mais pas par un modérateur valide
        }

        // NOUVELLE VÉRIFICATION: Vérifier si ce client est déjà assigné à un autre modérateur pour ce profil
        $existingAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->whereRaw("JSON_CONTAINS(conversation_ids, ?)", [json_encode($clientId)])
            ->first();

        if ($existingAssignment) {
            // Le client est déjà assigné à un modérateur pour ce profil
            $existingModerator = User::find($existingAssignment->user_id);

            Log::warning("Client déjà attribué à un autre modérateur pour ce profil", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'current_moderator' => $existingAssignment->user_id
            ]);

            // Retourner le modérateur existant au lieu d'en assigner un nouveau
            return $existingModerator;
        }

        // Trouver le modérateur le moins occupé qui a déjà ce profil assigné
        $moderator = $this->findLeastBusyModerator($clientId, $profileId);

        // Si aucun modérateur n'est trouvé, chercher n'importe quel modérateur actif
        if (!$moderator) {
            $moderator = User::where('type', 'moderateur')
                ->where('status', 'active')
                ->whereDoesntHave('moderatorProfileAssignments', function ($query) {
                    $query->where('is_active', true)
                        ->where('is_primary', true);
                })
                ->first();

            // Si un modérateur sans profil principal est trouvé, lui assigner ce profil
            if ($moderator) {
                $this->assignProfileToModerator($moderator->id, $profileId, true);
            } else {
                // Chercher un modérateur avec une charge de travail faible
                $moderator = User::where('type', 'moderateur')
                    ->where('status', 'active')
                    ->whereHas('moderatorProfileAssignments', function ($query) {
                        $query->where('is_active', true)
                            ->where('active_conversations_count', '<', 3);
                    })
                    ->first();
            }
        }

        if (!$moderator) {
            Log::warning("Aucun modérateur disponible pour l'assignation du client", [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ]);
            return null; // Aucun modérateur disponible
        }

        // Verrouiller le client pour ce modérateur
        $this->profileLockService->lockClient($clientId, $profileId, $moderator->id, 30);

        // Ajouter le client aux conversations du modérateur
        $assignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            $success = $assignment->addConversation($clientId);

            if ($success) {
                Log::info("Client assigné avec succès au modérateur", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderator->id
                ]);

                // Déclencher l'événement d'assignation de client
                $this->triggerClientAssignedEvent($moderator, $clientId, $profileId);
            } else {
                Log::warning("Échec de l'ajout du client à la conversation", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderator->id
                ]);
            }
        } else {
            Log::warning("Aucune assignation trouvée pour ajouter le client", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => $moderator->id
            ]);
        }

        return $moderator;
    }

    public function assignClientToModerator2($clientId, $profileId)
    {
        // Vérifier si le client est déjà verrouillé pour ce profil
        if ($this->profileLockService->isClientLocked($clientId, $profileId)) {
            $lockInfo = $this->profileLockService->getLockInfo($clientId, 'client');
            if (isset($lockInfo['moderator_id'])) {
                Log::info("Client déjà verrouillé, utilisation du modérateur verrouillé", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $lockInfo['moderator_id']
                ]);
                return User::find($lockInfo['moderator_id']);
            }
        }

        // Empêcher la double approche
        if (!$this->preventDoubleInitiation($clientId, $profileId)) {
            Log::warning("Double initiation empêchée", [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ]);
            return null;
        }

        // Vérifier si ce client est déjà assigné à un modérateur pour ce profil spécifique
        $existingAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->get();

        foreach ($existingAssignments as $assignment) {
            $conversations = $assignment->conversation_ids ?? [];
            if (in_array($clientId, $conversations)) {
                // Ce client est déjà assigné à un modérateur pour ce profil
                $moderator = User::find($assignment->user_id);

                // Verrouiller le client pour ce modérateur
                $this->profileLockService->lockClient($clientId, $profileId, $assignment->user_id, 30);

                return $moderator;
            }
        }

        // Trouver tous les modérateurs qui gèrent ce profil
        $assignments = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->with('user')
            ->get();

        if ($assignments->isEmpty()) {
            // Aucun modérateur n'a ce profil, trouver un modérateur disponible
            $availableModerator = $this->findLeastBusyModerator($clientId, $profileId);

            if (!$availableModerator) {
                return null;
            }

            // Assigner le profil au modérateur disponible
            $assignment = $this->assignProfileToModerator($availableModerator->id, $profileId);

            if (!$assignment) return null;

            $moderator = $availableModerator;
        } else {
            // Choisir le modérateur avec la priorité la plus élevée ou le moins de conversations
            $assignment = $assignments->sortByDesc('priority_score')
                ->sortBy('active_conversations_count')
                ->first();
            $moderator = $assignment->user;
        }

        // Verrouiller le client avant d'ajouter à la conversation
        $this->profileLockService->lockClient($clientId, $profileId, $moderator->id, 30);

        // Ajouter cette conversation à l'assignation
        $assignment->addConversation($clientId);

        // Déclencher l'événement d'assignation du client
        $this->triggerClientAssignedEvent($moderator, $clientId, $profileId);

        return $moderator;
    }

    /**
     * Verrouiller un profil pendant l'assignation
     */
    public function lockProfileForAssignment($profileId, $duration = 500)
    {
        return $this->profileLockService->lockProfile($profileId, null, $duration);
    }

    /**
     * Déverrouiller un profil
     */
    public function unlockProfile($profileId)
    {
        return $this->profileLockService->unlockProfile($profileId);
    }

    /**
     * Ajouter un modérateur à la file d'attente
     */
    public function addModeratorToQueue($moderatorId)
    {
        return $this->queueService->addToQueue($moderatorId);
    }

    /**
     * Retirer un modérateur de la file d'attente
     */
    public function removeModeratorFromQueue($moderatorId)
    {
        return $this->queueService->removeFromQueue($moderatorId);
    }

    /**
     * Obtenir le prochain modérateur de la file d'attente
     */
    public function getNextModeratorFromQueue()
    {
        return $this->queueService->getNextModerator();
    }

    /**
     * Gérer les connexions simultanées
     */
    public function handleSimultaneousConnections($moderatorIds, $availableProfiles)
    {
        return $this->conflictService->handleConnectionCollision($moderatorIds, $availableProfiles);
    }

    /**
     * Empêcher la double initiation de conversation client
     */
    public function preventDoubleInitiation($clientId, $profileId)
    {
        // Vérifier si le client est déjà en conversation avec ce profil
        $existing = Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->exists();

        if (!$existing) {
            // Vérifier si la combinaison client-profil est valide
            return $this->validateUniqueClientProfileAssignment($clientId, $profileId);
        }

        return true;
    }

    /**
     * Rééquilibrer la charge des modérateurs
     */
    public function rebalanceModeratorLoad()
    {
        return $this->loadBalancingService->rebalanceActiveAssignments();
    }

    /**
     * Résoudre les conflits d'attribution
     */
    public function resolveAttributionConflicts()
    {
        return $this->conflictService->validateAssignmentIntegrity();
    }

    /**
     * Valider l'assignation unique client-profil
     */
    public function validateUniqueClientProfileAssignment($clientId, $profileId)
    {
        if (!$clientId || !$profileId) {
            return true;
        }

        // Vérifier si ce client parle déjà avec un autre profil
        $otherProfileConversations = Message::where('client_id', $clientId)
            ->where('profile_id', '!=', $profileId)
            ->exists();

        if ($otherProfileConversations) {
            Log::warning("Client déjà assigné à un autre profil", [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ]);
            return false;
        }

        return true;
    }

    /**
     * Déclencher l'événement de profil assigné en utilisant la version plus récente
     */
    private function triggerProfileAssignedEvent($moderator, $profile, $assignmentId, $isPrimary, $oldModeratorId = null, $reason = null)
    {
        // Déclencher l'événement d'assignation de profil
        event(new \App\Events\ProfileAssigned(
            $moderator,
            $profile->id ?? $profile,
            $assignmentId,
            $oldModeratorId,
            $reason
        ));
    }

    /**
     * Déclencher l'événement de client assigné en utilisant la version plus récente
     */
    private function triggerClientAssignedEvent($moderator, $clientId, $profileId)
    {
        // Utilisation des anciens et nouveaux formats d'événements pour la compatibilité ascendante
        event(new ClientAssigned($moderator, $clientId, $profileId));
    }

    /**
     * Trouve le profil avec les messages les plus urgents
     */

    public function findMostUrgentProfile()
    {
        // Trouver les profils avec des messages non lus de clients
        $profilesWithUnreadMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->select('profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        if (empty($profilesWithUnreadMessages)) {
            // Si aucun message non lu, prendre n'importe quel profil disponible
            return Profile::where('status', 'active')
                ->inRandomOrder()
                ->value('id');
        }

        // Compter les messages non lus par profil pour déterminer la priorité
        $profileCounts = [];
        foreach ($profilesWithUnreadMessages as $profileId) {
            $count = Message::where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->count();

            $profileCounts[$profileId] = $count;
        }

        // Trier par nombre de messages non lus (du plus grand au plus petit)
        arsort($profileCounts);

        // Retourner le profil avec le plus de messages non lus
        return key($profileCounts);
    }

    /**
     * Obtient la liste des clients ayant besoin d'une réponse
     */
    public function getClientsNeedingResponse($urgentOnly = false)
    {
        $query = Message::select('client_id', 'profile_id', 'created_at')
            ->where('is_from_client', true)
            ->whereNull('read_at');

        if ($urgentOnly) {
            $query->where('created_at', '>', now()->subHours(2));
        }

        // Obtenir le dernier message de chaque conversation client-profil
        $latestMessages = $query->orderBy('created_at', 'desc')
            ->get()
            ->unique(function ($item) {
                return $item['client_id'] . '-' . $item['profile_id'];
            });

        return $latestMessages->map(function ($message) use ($urgentOnly) {
            return [
                'client_id' => $message->client_id,
                'profile_id' => $message->profile_id,
                'created_at' => $message->created_at,
                'is_urgent' => $message->created_at > now()->subHours(2)
            ];
        });
    }

    /**
     * traiter spécifiquement les clients en attente de réponse
     */
    public function processClientsNeedingResponse()
    {
        // Récupérer les clients ayant besoin d'une réponse
        $clientsNeedingResponse = $this->getClientsNeedingResponse();

        Log::info("[DEBUG] Clients nécessitant une réponse", [
            'client_count' => $clientsNeedingResponse->count()
        ]);

        $assignedCount = 0;

        foreach ($clientsNeedingResponse as $client) {
            // Assigner le client à un modérateur
            $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

            if ($moderator) {
                $assignedCount++;
                Log::info("Client assigné à un modérateur", [
                    'client_id' => $client['client_id'],
                    'profile_id' => $client['profile_id'],
                    'moderator_id' => $moderator->id
                ]);
            }
        }

        return $assignedCount;
    }

    /**
     * Libère les profils qui ont reçu une réponse
     */
    public function releaseRespondedProfiles($moderator)
    {
        $assignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->get();

        $released = 0;

        foreach ($assignments as $assignment) {
            $conversationIds = $assignment->conversation_ids ?? [];
            $updatedConversations = false;

            foreach ($conversationIds as $clientId) {
                // Vérifier si le dernier message est du modérateur
                $lastMessage = Message::where('client_id', $clientId)
                    ->where('profile_id', $assignment->profile_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Si le dernier message est du modérateur et date de plus de 30 minutes, libérer cette conversation
                if ($lastMessage && !$lastMessage->is_from_client && $lastMessage->created_at < now()->subMinutes(30)) {
                    $assignment->removeConversation($clientId);
                    $updatedConversations = true;
                    $released++;

                    Log::info("Conversation libérée pour inactivité client", [
                        'moderator_id' => $moderator->id,
                        'profile_id' => $assignment->profile_id,
                        'client_id' => $clientId,
                        'last_message_time' => $lastMessage->created_at
                    ]);
                }
            }

            if ($updatedConversations) {
                $assignment->save();
            }
        }

        Log::info("Profils libérés", [
            'moderator_id' => $moderator->id,
            'conversations_released' => $released
        ]);

        return $released;
    }

    /**
     * Réassigne les profils inactifs aux modérateurs disponibles
     * 
     * @param int $thresholdMinutes Nombre de minutes d'inactivité avant réassignation (défaut: 1)
     * @return int Nombre de profils réassignés
     */
    public function reassignInactiveProfiles2($thresholdMinutes = 1)
    {
        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Récupère les assignations actives inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where('last_activity', '<', $inactiveTime)
                    ->orWhereNull('last_activity');
            })
            ->with(['user', 'profile'])
            ->get();

        // Log de début de processus
        Log::info("Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        $reassigned = 0;

        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;
            $oldModeratorId = $assignment->user_id;

            // Vérifie les messages non lus pour ce profil
            $hasUnreadMessages = Message::where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->exists();

            // Récupère les profils en attente de modérateur
            $pendingProfiles = $this->getProfilesWithPendingMessages();

            // Désactive l'assignation si nécessaire
            if ($hasUnreadMessages || !empty($pendingProfiles)) {
                // Met à jour le suivi d'activité avant désactivation
                $assignment->update([
                    'last_activity_check' => now(),
                    'is_active' => false
                ]);

                Log::info("Assignation désactivée (inactivité)", [
                    'assignment_id' => $assignment->id,
                    'moderator_id' => $oldModeratorId,
                    'profile_id' => $profileId,
                    'timestamp' => now()->toDateTimeString()
                ]);

                // Réassignation du profil si messages non lus
                if ($hasUnreadMessages) {
                    // Passer l'ID du modérateur inactif pour l'exclure de la recherche
                    $availableModerator = $this->findLeastBusyModerator(null, $profileId, $oldModeratorId);

                    if ($availableModerator) {
                        $newAssignment = $this->assignProfileToModerator($availableModerator->id, $profileId, true);

                        if ($newAssignment) {
                            $reassigned++;

                            Log::info("Profil réassigné (inactivité)", [
                                'profile_id' => $profileId,
                                'old_moderator' => $oldModeratorId,
                                'new_moderator' => $availableModerator->id,
                                'timestamp' => now()->toDateTimeString()
                            ]);

                            // Notification WebSocket
                            $this->triggerProfileAssignedEvent(
                                $availableModerator,
                                $profileId,
                                $newAssignment->id,
                                true,
                                $oldModeratorId,
                                'inactivity'
                            );
                        }
                    } else {
                        Log::info("Pas d'autre modérateur disponible pour réassignation", [
                            'profile_id' => $profileId,
                            'old_moderator' => $oldModeratorId,
                            'timestamp' => now()->toDateTimeString()
                        ]);
                    }
                }

                // Attribution d'un nouveau profil au modérateur libéré
                if (!empty($pendingProfiles)) {
                    // Filtrer pour ne pas réattribuer le même profil
                    $filteredProfiles = array_filter($pendingProfiles, function ($pendingProfileId) use ($profileId) {
                        return $pendingProfileId != $profileId;
                    });

                    if (!empty($filteredProfiles)) {
                        $pendingProfileId = reset($filteredProfiles);
                        $newAssignment = $this->assignProfileToModerator($oldModeratorId, $pendingProfileId);

                        if ($newAssignment) {
                            Log::info("Nouveau profil attribué au modérateur inactif", [
                                'moderator_id' => $oldModeratorId,
                                'profile_id' => $pendingProfileId,
                                'timestamp' => now()->toDateTimeString()
                            ]);
                        }
                    } else {
                        Log::info("Aucun autre profil disponible pour le modérateur inactif", [
                            'moderator_id' => $oldModeratorId,
                            'timestamp' => now()->toDateTimeString()
                        ]);
                    }
                }
            }
        }

        return $reassigned;
    }

    /**
     * Réassigne les profils inactifs aux modérateurs disponibles
     * 
     * @param int $thresholdMinutes Nombre de minutes d'inactivité avant réassignation (défaut: 1)
     * @return int Nombre de profils réassignés
     */
    /* public function reassignInactiveProfiles($thresholdMinutes = 1)
    {
        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Récupère les assignations actives inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where('last_activity', '<', $inactiveTime)
                    ->orWhereNull('last_activity');
            })
            ->with(['user', 'profile'])
            ->get();

        // Log de début de processus
        Log::info("Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        $reassigned = 0;

        // Récupérer tous les profils avec des messages en attente une seule fois
        $pendingProfiles = $this->getProfilesWithPendingMessages();

        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;
            $oldModeratorId = $assignment->user_id;

            // Désactiver l'assignation immédiatement
            $assignment->update([
                'last_activity_check' => now(),
                'is_active' => false
            ]);

            Log::info("Assignation désactivée (inactivité)", [
                'assignment_id' => $assignment->id,
                'moderator_id' => $oldModeratorId,
                'profile_id' => $profileId,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Vérifie les messages non lus pour ce profil
            $hasUnreadMessages = Message::where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->exists();

            // Réassignation du profil si messages non lus
            if ($hasUnreadMessages) {
                // Passer l'ID du modérateur inactif pour l'exclure de la recherche
                $availableModerator = $this->findLeastBusyModerator(null, $profileId, $oldModeratorId);

                if ($availableModerator) {
                    $newAssignment = $this->assignProfileToModerator($availableModerator->id, $profileId, true);

                    if ($newAssignment) {
                        $reassigned++;

                        Log::info("Profil réassigné (inactivité)", [
                            'profile_id' => $profileId,
                            'old_moderator' => $oldModeratorId,
                            'new_moderator' => $availableModerator->id,
                            'timestamp' => now()->toDateTimeString()
                        ]);

                        // Notification WebSocket
                        $this->triggerProfileAssignedEvent(
                            $availableModerator,
                            $profileId,
                            $newAssignment->id,
                            true,
                            $oldModeratorId,
                            'inactivity'
                        );
                    }
                }
            }

            // Attribution d'un nouveau profil au modérateur libéré - TOUJOURS TENTER, même sans messages en attente
            if (!empty($pendingProfiles)) {
                // Filtrer pour ne pas réattribuer le même profil
                $filteredProfiles = array_values(array_filter($pendingProfiles, function ($pendingProfileId) use ($profileId) {
                    return $pendingProfileId != $profileId;
                }));

                if (!empty($filteredProfiles)) {
                    $pendingProfileId = $filteredProfiles[0]; // Utiliser le premier profil disponible
                    $newAssignment = $this->assignProfileToModerator($oldModeratorId, $pendingProfileId, true);

                    if ($newAssignment) {
                        Log::info("Nouveau profil attribué au modérateur inactif", [
                            'moderator_id' => $oldModeratorId,
                            'profile_id' => $pendingProfileId,
                            'timestamp' => now()->toDateTimeString()
                        ]);

                        // Notification WebSocket pour informer le frontend
                        $moderator = User::find($oldModeratorId);
                        if ($moderator) {
                            $this->triggerProfileAssignedEvent(
                                $moderator,
                                $pendingProfileId,
                                $newAssignment->id,
                                true,
                                null,
                                'new_assignment_after_inactivity'
                            );
                        }
                    }
                } else {
                    Log::info("Aucun autre profil disponible pour le modérateur inactif", [
                        'moderator_id' => $oldModeratorId,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
            }
        }

        return $reassigned;
    } */

    public function reassignInactiveProfiles($thresholdMinutes = 1)
    {
        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Récupère les assignations actives inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where('last_activity', '<', $inactiveTime)
                    ->orWhereNull('last_activity');
            })
            ->with(['user', 'profile'])
            ->get();

        // Log de début de processus
        Log::info("Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        $reassigned = 0;
        $pendingProfiles = $this->getProfilesWithPendingMessages();

        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;
            $oldModeratorId = $assignment->user_id;

            try {
                DB::beginTransaction();

                // Désactiver l'assignation immédiatement
                $assignment->update([
                    'last_activity_check' => now(),
                    'is_active' => false
                ]);

                Log::info("Assignation désactivée (inactivité)", [
                    'assignment_id' => $assignment->id,
                    'moderator_id' => $oldModeratorId,
                    'profile_id' => $profileId,
                    'timestamp' => now()->toDateTimeString()
                ]);

                // Forcer le déverrouillage du profil s'il est verrouillé
                if ($this->profileLockService->isProfileLocked($profileId)) {
                    $this->profileLockService->unlockProfile($profileId);
                    Log::info("Profil déverrouillé de force pour réattribution", [
                        'profile_id' => $profileId,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }

                // Vérifie les messages non lus pour ce profil
                $hasUnreadMessages = Message::where('profile_id', $profileId)
                    ->where('is_from_client', true)
                    ->whereNull('read_at')
                    ->exists();

                // Réassignation du profil si messages non lus
                if ($hasUnreadMessages) {
                    // Passer l'ID du modérateur inactif pour l'exclure de la recherche
                    $availableModerator = $this->findLeastBusyModerator(null, $profileId, $oldModeratorId);

                    if ($availableModerator) {
                        $newAssignment = $this->assignProfileToModerator($availableModerator->id, $profileId, true);

                        if ($newAssignment) {
                            $reassigned++;

                            Log::info("Profil réassigné (inactivité)", [
                                'profile_id' => $profileId,
                                'old_moderator' => $oldModeratorId,
                                'new_moderator' => $availableModerator->id,
                                'timestamp' => now()->toDateTimeString()
                            ]);

                            // Notification WebSocket
                            $this->triggerProfileAssignedEvent(
                                $availableModerator,
                                $profileId,
                                $newAssignment->id,
                                true,
                                $oldModeratorId,
                                'inactivity'
                            );
                        }
                    }
                }

                // Attribution d'un nouveau profil au modérateur libéré - TOUJOURS TENTER
                if (!empty($pendingProfiles)) {
                    // Filtrer pour ne pas réattribuer le même profil
                    $filteredProfiles = array_values(array_filter($pendingProfiles, function ($pendingProfileId) use ($profileId) {
                        return $pendingProfileId != $profileId;
                    }));

                    if (!empty($filteredProfiles)) {
                        $pendingProfileId = $filteredProfiles[0]; // Utiliser le premier profil disponible

                        // Forcer le déverrouillage du nouveau profil aussi si nécessaire
                        if ($this->profileLockService->isProfileLocked($pendingProfileId)) {
                            $this->profileLockService->unlockProfile($pendingProfileId);
                        }

                        $newAssignment = $this->assignProfileToModerator($oldModeratorId, $pendingProfileId, true);

                        if ($newAssignment) {
                            Log::info("Nouveau profil attribué au modérateur inactif", [
                                'moderator_id' => $oldModeratorId,
                                'profile_id' => $pendingProfileId,
                                'timestamp' => now()->toDateTimeString()
                            ]);

                            // Notification WebSocket
                            $moderator = User::find($oldModeratorId);
                            if ($moderator) {
                                $this->triggerProfileAssignedEvent(
                                    $moderator,
                                    $pendingProfileId,
                                    $newAssignment->id,
                                    true,
                                    null,
                                    'new_assignment_after_inactivity'
                                );
                            }
                        }
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Erreur lors de la réassignation du profil", [
                    'error' => $e->getMessage(),
                    'profile_id' => $profileId,
                    'moderator_id' => $oldModeratorId,
                    'timestamp' => now()->toDateTimeString()
                ]);
            }
        }

        return $reassigned;
    }


    /**
     * Récupère les profils avec des messages en attente
     * Cette méthode est publique pour être utilisée par d'autres services
     */
    public function getProfilesWithPendingMessages()
    {
        // Sous-requête pour obtenir le dernier message de chaque conversation (client-profil)
        $latestMessages = DB::table('messages')
            ->select(
                'client_id',
                'profile_id',
                DB::raw('MAX(created_at) as latest_message_at')
            )
            ->groupBy('client_id', 'profile_id');

        // Requête principale pour trouver les conversations où le dernier message est du client
        $pendingProfiles = DB::table('messages as m')
            ->joinSub($latestMessages, 'latest', function ($join) {
                $join->on('m.client_id', '=', 'latest.client_id')
                    ->on('m.profile_id', '=', 'latest.profile_id')
                    ->on('m.created_at', '=', 'latest.latest_message_at');
            })
            ->where('m.is_from_client', true)
            ->whereNull('m.read_at')
            ->select('m.profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        Log::info("Profils avec messages en attente", [
            'count' => count($pendingProfiles),
            'profiles' => $pendingProfiles
        ]);

        return $pendingProfiles;
    }

    /**
     * Récupère tous les profils assignés à un modérateur
     * 
     * @param User $moderator Le modérateur dont on veut récupérer les profils
     * @return \Illuminate\Database\Eloquent\Collection Collection des profils assignés
     */
    public function getAllAssignedProfiles($moderator)
    {
        // Récupérer les IDs des profils assignés à ce modérateur
        $profileIds = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer les profils correspondants avec leurs photos
        $profiles = Profile::with('photos')
            ->whereIn('id', $profileIds)
            ->get();

        return $profiles;
    }

    /**
     * Met à jour la dernière activité d'un modérateur pour tous ses profils assignés
     * 
     * @param User $moderator Le modérateur dont on veut mettre à jour l'activité
     * @return bool Succès de l'opération
     */
    public function updateLastActivity($moderator)
    {
        ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->update([
                'last_activity' => now(),
                'last_activity_check' => now()
            ]);

        return true;
    }
}
