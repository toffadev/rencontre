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
use App\Services\TimeoutManagementService;

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
    protected $timeoutService;

    public function __construct(
        ?ProfileLockService $profileLockService = null,
        ?ModeratorQueueService $queueService = null,
        ?ConflictResolutionService $conflictService = null,
        ?LoadBalancingService $loadBalancingService = null,
        ?TimeoutManagementService $timeoutService = null
    ) {
        $this->profileLockService = $profileLockService ?? new ProfileLockService();
        $this->queueService = $queueService ?? new ModeratorQueueService();
        $this->conflictService = $conflictService ?? new ConflictResolutionService();
        $this->loadBalancingService = $loadBalancingService ?? new LoadBalancingService();
        $this->timeoutService = $timeoutService ?? new TimeoutManagementService();
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

            // Créer ou mettre à jour l'assignation
            if ($existingAssignment) {
                $existingAssignment->is_active = true;
                $existingAssignment->last_activity = now();
                $existingAssignment->last_activity_check = now();

                if ($existingAssignment->queue_position) {
                    $this->removeModeratorFromQueue($moderatorId);
                    $existingAssignment->queue_position = null;
                }

                if ($isPrimary) {
                    ModeratorProfileAssignment::where('user_id', $moderatorId)
                        ->where('is_primary', true)
                        ->where('id', '!=', $existingAssignment->id)
                        ->update(['is_primary' => false]);

                    $existingAssignment->is_primary = true;
                }

                $existingAssignment->save();
                $this->unlockProfile($profileId);

                // Démarrer/redémarrer le timer
                $this->timeoutService->startInactivityTimer($moderatorId, $profileId);

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
                'last_message_sent' => now(), // Initialisation correcte
                'last_typing' => now(),       // Initialisation correcte
                'queue_position' => null,
                'locked_clients' => []
            ]);

            $assignment->save();
            $this->removeModeratorFromQueue($moderatorId);
            $this->rebalanceModeratorLoad();

            // Démarrer le timer d'inactivité
            $this->timeoutService->startInactivityTimer($moderatorId, $profileId);

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
     * Désactiver une assignation et annuler son timer
     */
    public function deactivateAssignment($moderatorId, $profileId)
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            try {
                DB::beginTransaction();

                $assignment->is_active = false;
                $assignment->save();

                // Annuler le timer associé
                $this->timeoutService->cancelTimer($moderatorId, $profileId);

                DB::commit();
                return $assignment;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Erreur lors de la désactivation de l'assignation", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $profileId,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        return null;
    }

    /**
     * Assigner un profil à un modérateur disponible
     */
    public function assignProfileToAvailableModerator($profileId)
    {
        $availableModerator = $this->findLeastBusyModerator(null, $profileId);

        if (!$availableModerator) {
            Log::warning("Aucun modérateur disponible pour l'assignation", [
                'profile_id' => $profileId
            ]);
            return null;
        }

        return $this->assignProfileToModerator($availableModerator->id, $profileId, true);
    }

    /**
     * Traiter les messages non assignés avec les nouveaux scénarios de priorité
     */
    public function processUnassignedMessages($urgentOnly = false)
    {
        // Statistiques détaillées sur les assignations
        $stats = [
            'new' => 0,        // Nouveaux clients assignés (qui n'avaient pas de modérateur)
            'reassigned' => 0, // Clients réassignés (changement de modérateur)
            'confirmed' => 0,  // Clients dont l'assignation existante a été confirmée
            'total' => 0       // Total des clients traités
        ];

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

                    // Vérifier si le client est déjà assigné à un modérateur
                    $existingAssignment = ModeratorProfileAssignment::where('profile_id', $client['profile_id'])
                        ->where('is_active', true)
                        ->first();

                    $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

                    if ($moderator) {
                        // Déterminer le type d'assignation
                        if (!$existingAssignment) {
                            $stats['new']++;
                            Log::info("Client nouvellement assigné", [
                                'client_id' => $client['client_id'],
                                'profile_id' => $client['profile_id'],
                                'moderator_id' => $moderator->id
                            ]);
                        } elseif ($existingAssignment->user_id != $moderator->id) {
                            $stats['reassigned']++;
                            Log::info("Client réassigné à un nouveau modérateur", [
                                'client_id' => $client['client_id'],
                                'profile_id' => $client['profile_id'],
                                'old_moderator' => $existingAssignment->user_id,
                                'new_moderator' => $moderator->id
                            ]);
                        } else {
                            $stats['confirmed']++;
                            Log::info("Assignation de client confirmée", [
                                'client_id' => $client['client_id'],
                                'profile_id' => $client['profile_id'],
                                'moderator_id' => $moderator->id
                            ]);
                        }

                        $stats['total']++;
                        $clientsNeedingResponse->forget($clientIndex);
                    }
                }
            }
        }

        // Traiter les clients restants par priorité
        foreach ($clientsNeedingResponse as $client) {
            // Vérifier si le client est déjà assigné à un modérateur
            $existingAssignment = ModeratorProfileAssignment::where('profile_id', $client['profile_id'])
                ->where('is_active', true)
                ->first();

            // Assigner le client à un modérateur
            $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

            if ($moderator) {
                // Déterminer le type d'assignation
                if (!$existingAssignment) {
                    $stats['new']++;
                    Log::info("Client nouvellement assigné", [
                        'client_id' => $client['client_id'],
                        'profile_id' => $client['profile_id'],
                        'moderator_id' => $moderator->id
                    ]);
                } elseif ($existingAssignment->user_id != $moderator->id) {
                    $stats['reassigned']++;
                    Log::info("Client réassigné à un nouveau modérateur", [
                        'client_id' => $client['client_id'],
                        'profile_id' => $client['profile_id'],
                        'old_moderator' => $existingAssignment->user_id,
                        'new_moderator' => $moderator->id
                    ]);
                } else {
                    $stats['confirmed']++;
                    Log::info("Assignation de client confirmée", [
                        'client_id' => $client['client_id'],
                        'profile_id' => $client['profile_id'],
                        'moderator_id' => $moderator->id
                    ]);
                }

                $stats['total']++;

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

        // Log des statistiques détaillées
        Log::info("Résumé des assignations de clients", [
            'nouveaux' => $stats['new'],
            'reassignés' => $stats['reassigned'],
            'confirmés' => $stats['confirmed'],
            'total' => $stats['total']
        ]);

        return $stats;
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
     * Un client a besoin d'une réponse si :
     * - Son dernier message n'a pas reçu de réponse du profil
     */
    public function getClientsNeedingResponse($urgentOnly = false)
    {
        // Étape 1: Obtenir le dernier message de chaque conversation (client-profil)
        $latestMessagesSubquery = DB::table('messages')
            ->select(
                'client_id',
                'profile_id',
                DB::raw('MAX(created_at) as latest_message_at')
            )
            ->groupBy('client_id', 'profile_id');

        // Étape 2: Récupérer les conversations où le dernier message vient du client
        $clientsWithLastMessageQuery = DB::table('messages as m')
            ->joinSub($latestMessagesSubquery, 'latest', function ($join) {
                $join->on('m.client_id', '=', 'latest.client_id')
                    ->on('m.profile_id', '=', 'latest.profile_id')
                    ->on('m.created_at', '=', 'latest.latest_message_at');
            })
            ->join('users', 'm.client_id', '=', 'users.id')
            ->where('m.is_from_client', true)
            ->select(
                'm.client_id',
                'm.profile_id',
                'm.created_at',
                'users.is_online',
                'users.last_activity_at'
            );

        // Filtrer par urgence si demandé
        if ($urgentOnly) {
            $clientsWithLastMessageQuery->where('m.created_at', '>', now()->subHours(2));
        }

        $clientsWithLastMessage = $clientsWithLastMessageQuery->get();

        // Ajouter des logs pour vérifier les clients et leur statut
        Log::info("Clients avec dernier message du client", [
            'count' => $clientsWithLastMessage->count(),
            'details' => $clientsWithLastMessage
        ]);

        // Traiter les clients pour déterminer leur niveau d'activité
        $clientsNeedingResponse = collect();

        foreach ($clientsWithLastMessage as $client) {
            // Déterminer le niveau d'activité du client
            $activityLevel = $this->determineClientActivityLevel($client->is_online, $client->last_activity_at);

            // Ajouter à la collection avec le niveau d'activité
            $clientsNeedingResponse->push([
                'client_id' => $client->client_id,
                'profile_id' => $client->profile_id,
                'created_at' => $client->created_at,
                'is_online' => $client->is_online,
                'activity_level' => $activityLevel
            ]);
        }

        // Statistiques par niveau d'activité
        $activityBreakdown = [
            'actifs' => $clientsNeedingResponse->where('activity_level', 1)->count(),
            'semi_actifs' => $clientsNeedingResponse->where('activity_level', 2)->count(),
            'inactifs' => $clientsNeedingResponse->where('activity_level', 3)->count()
        ];

        Log::info("Clients nécessitant une réponse", [
            'total_clients' => $clientsNeedingResponse->count(),
            'activity_breakdown' => $activityBreakdown
        ]);

        return $clientsNeedingResponse;
    }


    /**
     * Détermine le niveau d'activité d'un client selon la classification définie
     * @param bool $isOnline Si le client est connecté
     * @param string|null $lastActivityAt Horodatage de la dernière activité du client
     * @return int 1=actif (priorité absolue), 2=semi-actif (priorité élevée), 3=inactif (priorité standard)
     */
    private function determineClientActivityLevel($isOnline, $lastActivityAt)
    {
        // RÈGLE 1: Client inactif (non connecté) = priorité standard (3)
        if (!$isOnline) {
            return 3; // Client inactif (non connecté)
        }

        // Client est connecté, vérifier son niveau d'activité
        if (!$lastActivityAt) {
            return 2; // Client semi-actif par défaut si on ne connaît pas sa dernière activité
        }

        // Convertir la chaîne de date en objet Carbon
        $lastActivity = Carbon::parse($lastActivityAt);
        $now = now();

        // Calculer la durée d'inactivité en minutes
        $inactivityDuration = $lastActivity->diffInMinutes($now);

        // Logger pour debug
        /* Log::debug("Calcul du niveau d'activité client", [
            'is_online' => $isOnline,
            'last_activity_at' => $lastActivityAt,
            'inactivity_duration' => $inactivityDuration,
            'now' => $now->toDateTimeString()
        ]); */

        // RÈGLE 2: Client actif = activité récente (moins de 2 minutes)
        if ($inactivityDuration < 2) {
            return 1; // Client actif (priorité absolue)
        }

        // RÈGLE 3: Client semi-actif = connecté mais sans activité récente
        // Attention: si la dernière activité date de plus de 30 minutes, 
        // le client devrait être considéré comme inactif même s'il est marqué comme connecté
        if ($inactivityDuration > 30) {
            return 3; // Client techniquement connecté mais inactif depuis trop longtemps
        }

        return 2; // Client semi-actif (priorité élevée)
    }



    /**
     * Traiter spécifiquement les clients en attente de réponse
     * Priorise les clients actifs et connectés
     */
    public function processClientsNeedingResponse()
    {
        // Récupérer les clients ayant besoin d'une réponse (déjà triés par niveau d'activité)
        $clientsNeedingResponse = $this->getClientsNeedingResponse();

        // Grouper par niveau d'activité pour le traitement par priorité
        $clientsByActivity = $clientsNeedingResponse->groupBy('activity_level');

        // Préparer les statistiques pour le logging
        $activityStats = [
            1 => $clientsByActivity[1] ?? collect(),  // Clients actifs
            2 => $clientsByActivity[2] ?? collect(),  // Clients semi-actifs
            3 => $clientsByActivity[3] ?? collect(),  // Clients inactifs
        ];

        Log::info("Clients nécessitant une réponse", [
            'total_clients' => $clientsNeedingResponse->count(),
            'activity_breakdown' => [
                'actifs' => $activityStats[1]->count(),
                'semi_actifs' => $activityStats[2]->count(),
                'inactifs' => $activityStats[3]->count()
            ]
        ]);

        $assignedCount = 0;
        $activityNames = [1 => 'actif', 2 => 'semi-actif', 3 => 'inactif'];

        // Traiter les clients par ordre de priorité stricte
        foreach ([1, 2, 3] as $activityLevel) {
            $clients = $activityStats[$activityLevel];
            $activityName = $activityNames[$activityLevel];

            if ($clients->count() > 0) {
                Log::info("Traitement des clients de niveau {$activityLevel} ({$activityName})", [
                    'count' => $clients->count()
                ]);

                foreach ($clients as $client) {
                    // Assigner le client à un modérateur
                    $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

                    if ($moderator) {
                        $assignedCount++;

                        Log::info("Client {$activityName} assigné à un modérateur", [
                            'client_id' => $client['client_id'],
                            'profile_id' => $client['profile_id'],
                            'moderator_id' => $moderator->id,
                            'activity_level' => $activityLevel,
                            'is_online' => $client['is_online'],
                            'is_urgent' => $client['is_urgent'] ?? false
                        ]);
                    } else {
                        Log::warning("Impossible d'assigner un modérateur à un client {$activityName}", [
                            'client_id' => $client['client_id'],
                            'profile_id' => $client['profile_id']
                        ]);
                    }
                }
            }
        }

        Log::info("Traitement des clients terminé", [
            'clients_assignés' => $assignedCount,
            'clients_total' => $clientsNeedingResponse->count()
        ]);

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
    public function reassignInactiveProfiles($thresholdMinutes = 1)
    {
        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Récupère les assignations actives inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                // Un modérateur est inactif s'il n'a pas envoyé de message ET n'a pas tapé depuis le seuil
                $query->where(function ($q) use ($inactiveTime) {
                    // N'a pas envoyé de message récemment
                    $q->where('last_message_sent', '<', $inactiveTime)
                        ->orWhereNull('last_message_sent');
                })->where(function ($q) use ($inactiveTime) {
                    // ET n'a pas tapé récemment
                    $q->where('last_typing', '<', $inactiveTime)
                        ->orWhereNull('last_typing');
                });
            })
            ->with(['user', 'profile'])
            ->get();

        // Log de début de processus
        Log::info("Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString(),
            'critères' => 'Modérateurs sans message ET sans frappe depuis ' . $thresholdMinutes . ' minute(s)',
            'seuil_inactivité' => $inactiveTime->toDateTimeString()
        ]);

        $reassigned = 0;

        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;
            $oldModeratorId = $assignment->user_id;

            // Vérifions si le modérateur est toujours en ligne
            $moderatorIsOnline = User::where('id', $oldModeratorId)
                ->where('is_online', true)
                ->exists();

            if (!$moderatorIsOnline) {
                Log::info("Modérateur n'est plus en ligne, ignorer cette assignation", [
                    'moderator_id' => $oldModeratorId,
                    'profile_id' => $profileId
                ]);
                continue;
            }

            // Réassigner le profil indépendamment des messages non lus
            // car le modérateur est inactif - c'est la priorité du système de rotation

            // Désactiver l'assignation courante
            $assignment->update([
                'last_activity_check' => now(),
                'is_active' => false
            ]);

            // Annuler explicitement le timer d'inactivité pour l'ancienne assignation
            $this->timeoutService->cancelTimer($oldModeratorId, $profileId);

            Log::info("Assignation désactivée et timer annulé (inactivité)", [
                'assignment_id' => $assignment->id,
                'moderator_id' => $oldModeratorId,
                'profile_id' => $profileId,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Utiliser findAvailableModerator au lieu de findLeastBusyModerator
            // pour garantir l'exclusion du modérateur inactif
            $availableModerator = $this->findAvailableModerator($profileId, [$oldModeratorId]);

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

                    // Démarrer explicitement un nouveau timer d'inactivité pour la nouvelle assignation
                    $this->timeoutService->startInactivityTimer(
                        $availableModerator->id,
                        $profileId
                    );

                    Log::info("Nouveau timer d'inactivité démarré pour la nouvelle assignation", [
                        'new_moderator_id' => $availableModerator->id,
                        'profile_id' => $profileId
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

            // Récupérer les profils en attente
            $pendingProfiles = $this->getProfilesWithPendingMessages();

            // Attribuer un nouveau profil au modérateur libéré s'il est toujours en ligne
            if (!empty($pendingProfiles) && $moderatorIsOnline) {
                // Filtrer pour ne pas réattribuer le même profil
                $filteredProfiles = array_filter($pendingProfiles, function ($pendingProfileId) use ($profileId) {
                    return $pendingProfileId != $profileId;
                });

                if (!empty($filteredProfiles)) {
                    $pendingProfileId = reset($filteredProfiles);
                    $newAssignment = $this->assignProfileToModerator($oldModeratorId, $pendingProfileId);

                    if ($newAssignment) {
                        Log::info("Nouveau profil attribué au modérateur précédemment inactif", [
                            'moderator_id' => $oldModeratorId,
                            'profile_id' => $pendingProfileId,
                            'timestamp' => now()->toDateTimeString()
                        ]);
                    }
                } else {
                    Log::info("Aucun autre profil disponible pour le modérateur", [
                        'moderator_id' => $oldModeratorId,
                        'timestamp' => now()->toDateTimeString()
                    ]);
                }
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
        // Obtenir les modérateurs actifs et leur charge de travail
        $activeModeratorWorkloads = [];
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        foreach ($moderators as $moderator) {
            $workload = $this->calculateModeratorWorkload($moderator->id);
            $activeModeratorWorkloads[$moderator->id] = $workload;
        }

        Log::info("Modérateurs actifs et leur charge", [
            'count' => count($activeModeratorWorkloads),
            'statuses' => collect($activeModeratorWorkloads)->pluck('status', 'moderator_id')->toArray()
        ]);

        // Étape 1: Obtenir le dernier message de chaque conversation (client-profil)
        $latestMessagesSubquery = DB::table('messages')
            ->select(
                'client_id',
                'profile_id',
                DB::raw('MAX(created_at) as latest_message_at')
            )
            ->groupBy('client_id', 'profile_id');

        // Étape 2: Récupérer les conversations où le dernier message vient du client
        $clientsWithLastMessageQuery = DB::table('messages as m')
            ->joinSub($latestMessagesSubquery, 'latest', function ($join) {
                $join->on('m.client_id', '=', 'latest.client_id')
                    ->on('m.profile_id', '=', 'latest.profile_id')
                    ->on('m.created_at', '=', 'latest.latest_message_at');
            })
            ->where('m.is_from_client', true)
            ->select('m.profile_id', 'm.client_id')
            ->distinct();

        $clientsWithLastMessage = $clientsWithLastMessageQuery->get();

        // Étape 3: Filtrer pour ne garder que les profils sans modérateur assigné actif et disponible
        $pendingProfiles = [];
        $totalConversations = 0;

        foreach ($clientsWithLastMessage as $client) {
            // Vérifier si le profil a un modérateur assigné, actif et non surchargé
            $hasActiveAvailableModerator = false;

            $assignments = ModeratorProfileAssignment::where('profile_id', $client->profile_id)
                ->where('is_active', true)
                ->get();

            foreach ($assignments as $assignment) {
                $moderatorId = $assignment->user_id;
                // Vérifier si ce modérateur est actif et non surchargé
                if (
                    isset($activeModeratorWorkloads[$moderatorId]) &&
                    $activeModeratorWorkloads[$moderatorId]['status'] !== 'surchargé'
                ) {
                    $hasActiveAvailableModerator = true;
                    break;
                }
            }

            // Si pas de modérateur assigné actif et disponible, ce profil a besoin d'une réponse
            if (!$hasActiveAvailableModerator && !in_array($client->profile_id, $pendingProfiles)) {
                $pendingProfiles[] = $client->profile_id;
                $totalConversations++;
            }
        }

        Log::info("Profils avec messages en attente", [
            'count' => count($pendingProfiles),
            'profiles' => $pendingProfiles,
            'total_client_conversations' => $totalConversations,
            'active_moderators' => count($activeModeratorWorkloads)
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

    public function rotateProfileAssignment($profileId, $currentModeratorId)
    {
        Log::info("Début de la rotation du profil", [
            'profile_id' => $profileId,
            'current_moderator_id' => $currentModeratorId
        ]);

        $profile = Profile::find($profileId);
        if (!$profile) {
            Log::warning("Profil non trouvé pour rotation", ['profile_id' => $profileId]);
            return null;
        }

        DB::beginTransaction();
        try {
            // 1. Vérifier et désactiver l'assignation actuelle
            $currentAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('user_id', $currentModeratorId)
                ->where('is_active', true)
                ->first();

            if (!$currentAssignment) {
                Log::warning("Aucune assignation active trouvée pour la rotation", [
                    'profile_id' => $profileId,
                    'moderator_id' => $currentModeratorId
                ]);
                DB::rollBack();
                return null;
            }

            $isPrimary = $currentAssignment->is_primary;
            $conversationIds = $currentAssignment->conversation_ids;
            $activeConversationsCount = $currentAssignment->active_conversations_count;

            $currentAssignment->is_active = false;
            $currentAssignment->ended_at = now();
            $currentAssignment->save();

            // Annuler le timer de l'ancien modérateur
            $this->timeoutService->cancelTimer($currentModeratorId, $profileId);

            Log::info("Assignation désactivée pour rotation", [
                'assignment_id' => $currentAssignment->id,
                'profile_id' => $profileId,
                'moderator_id' => $currentModeratorId
            ]);

            // 2. Trouver un nouveau modérateur disponible
            $newModerator = $this->findAvailableModerator($profileId, [$currentModeratorId]);
            if (!$newModerator) {
                Log::warning("Aucun modérateur disponible pour la rotation", [
                    'profile_id' => $profileId
                ]);
                DB::rollBack();
                return null;
            }

            $newModeratorId = $newModerator->id;
            Log::info("Nouveau modérateur trouvé pour rotation", [
                'profile_id' => $profileId,
                'new_moderator_id' => $newModeratorId
            ]);

            // 3. Réutiliser ou créer une assignation
            $existingAssignment = ModeratorProfileAssignment::where('user_id', $newModeratorId)
                ->where('profile_id', $profileId)
                ->first();

            if ($existingAssignment) {
                // Réactivation
                $existingAssignment->is_active = true;
                $existingAssignment->last_activity = now();
                $existingAssignment->last_activity_check = now();
                $existingAssignment->last_message_sent = now();
                $existingAssignment->last_typing = now();
                $existingAssignment->is_primary = $isPrimary;
                $existingAssignment->conversation_ids = $conversationIds ?? [];
                $existingAssignment->active_conversations_count = $activeConversationsCount ?? 0;
                $existingAssignment->save();

                $newAssignment = $existingAssignment;

                Log::info("Assignation existante réactivée", [
                    'assignment_id' => $newAssignment->id
                ]);
            } else {
                // Création d'une nouvelle assignation
                $newAssignment = new ModeratorProfileAssignment([
                    'user_id' => $newModeratorId,
                    'profile_id' => $profileId,
                    'is_active' => true,
                    'is_primary' => $isPrimary,
                    'conversation_ids' => $conversationIds ?? [],
                    'active_conversations_count' => $activeConversationsCount ?? 0,
                    'assigned_at' => now(),
                    'last_activity' => now(),
                    'last_activity_check' => now(),
                    'last_message_sent' => now(),
                    'last_typing' => now(),
                    'queue_position' => null
                ]);
                $newAssignment->save();

                Log::info("Nouvelle assignation créée", [
                    'assignment_id' => $newAssignment->id
                ]);
            }

            // 4. Démarrer le nouveau timer d’inactivité
            $this->timeoutService->startInactivityTimer($newModeratorId, $profileId);

            // 5. Déclencher l’événement enrichi
            $this->triggerProfileAssignedEvent(
                $newModerator,
                $profile,
                $newAssignment->id,
                $isPrimary,
                $currentModeratorId,
                'rotation'
            );

            DB::commit();

            Log::info("Rotation du profil réussie", [
                'profile_id' => $profileId,
                'old_moderator_id' => $currentModeratorId,
                'new_moderator_id' => $newModeratorId,
                'new_assignment_id' => $newAssignment->id
            ]);

            return $newAssignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la rotation du profil", [
                'profile_id' => $profileId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    private function findAvailableModerator($profileId, $excludeModeratorIds = [])
    {
        Log::info("Recherche d'un modérateur disponible", [
            'profile_id' => $profileId,
            'excluded_moderators' => $excludeModeratorIds
        ]);

        // Trouver les modérateurs actifs et en ligne
        $availableModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->whereNotIn('id', $excludeModeratorIds)
            ->get();

        Log::info("Modérateurs disponibles trouvés", [
            'count' => $availableModerators->count(),
            'moderator_ids' => $availableModerators->pluck('id')->toArray()
        ]);

        if ($availableModerators->isEmpty()) {
            return null;
        }

        // Compter les assignations actives pour chaque modérateur
        $moderatorsWithCount = [];
        foreach ($availableModerators as $moderator) {
            $activeAssignmentsCount = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->count();

            $moderatorsWithCount[$moderator->id] = [
                'moderator' => $moderator,
                'count' => $activeAssignmentsCount
            ];
        }

        // Trier par nombre d'assignations actives (croissant)
        uasort($moderatorsWithCount, function ($a, $b) {
            return $a['count'] <=> $b['count'];
        });

        // Prendre le premier (celui avec le moins d'assignations)
        $selectedModeratorData = reset($moderatorsWithCount);
        $selectedModerator = $selectedModeratorData['moderator'];

        Log::info("Modérateur sélectionné pour l'assignation", [
            'moderator_id' => $selectedModerator->id,
            'moderator_name' => $selectedModerator->name,
            'current_assignments' => $selectedModeratorData['count']
        ]);

        return $selectedModerator;
    }

    /**
     * Calcule la charge de travail d'un modérateur
     * @param int $moderatorId ID du modérateur
     * @return array Informations sur la charge de travail
     */
    public function calculateModeratorWorkload($moderatorId)
    {
        $assignments = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('is_active', true)
            ->get();

        $totalLoad = 0;
        $activeProfiles = 0;
        $activeConversations = 0;

        foreach ($assignments as $assignment) {
            $conversationCount = $assignment->active_conversations_count ?? 0;
            $conversationIds = $assignment->conversation_ids ?? [];

            if (is_string($conversationIds)) {
                try {
                    $conversationIds = json_decode($conversationIds, true) ?? [];
                } catch (\Exception $e) {
                    $conversationIds = [];
                }
            }

            $conversationCount = max($conversationCount, count($conversationIds));
            $totalLoad += $conversationCount;
            $activeProfiles++;
            $activeConversations += $conversationCount;
        }

        // Calcul du score: plus le score est bas, plus le modérateur est chargé
        // Facteurs: nombre de profils actifs et nombre de conversations actives
        $score = max(0, min(100, 100 - ($activeProfiles * 10) - ($activeConversations * 5)));

        return [
            'score' => $score,
            'active_profiles' => $activeProfiles,
            'conversations' => $activeConversations,
            'status' => $score > 60 ? 'disponible' : ($score > 30 ? 'occupé' : 'surchargé')
        ];
    }
}
