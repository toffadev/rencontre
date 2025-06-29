# Solution d'implémentation du système d'attribution et de rotation des profils

## 1. Fichiers à modifier

### `app/Models/ModeratorProfileAssignment.php`

**Méthodes à modifier :**

-   `addConversation($clientId)` : Ajouter vérification de verrouillage client avant ajout
-   `removeConversation($clientId)` : Déverrouiller le client lors de la suppression

**Nouvelles méthodes à ajouter :**

-   `lockClient($clientId, $duration = 30)` : Verrouiller un client pour empêcher double attribution
-   `unlockClient($clientId)` : Déverrouiller un client
-   `isClientLocked($clientId)` : Vérifier si un client est verrouillé
-   `getLockedClients()` : Récupérer la liste des clients verrouillés
-   `cleanExpiredLocks()` : Nettoyer les verrous expirés

**Nouvelles colonnes à ajouter via migration :**

-   `locked_clients` (JSON) : Stockage des clients verrouillés avec timestamps
-   `queue_position` (INTEGER) : Position dans la file d'attente d'attribution
-   `assigned_at` (TIMESTAMP) : Horodatage de l'attribution
-   `last_activity_check` (TIMESTAMP) : Dernière vérification d'activité

### `app/Services/ModeratorAssignmentService.php`

**Méthodes à modifier :**

-   `assignProfileToModerator()` : Intégrer le système de verrouillage et de file d'attente
-   `processUnassignedMessages()` : Gérer les priorités selon les nouveaux scénarios
-   `findLeastBusyModerator()` : Prendre en compte la file d'attente et les verrous
-   `assignClientToModerator()` : Ajouter contrôles de verrouillage client

**Nouvelles méthodes à ajouter :**

-   `lockProfileForAssignment($profileId, $duration = 500)` : Verrouiller un profil pendant attribution
-   `unlockProfile($profileId)` : Déverrouiller un profil
-   `addModeratorToQueue($moderatorId)` : Ajouter un modérateur à la file d'attente
-   `removeModeratorFromQueue($moderatorId)` : Retirer un modérateur de la file d'attente
-   `getNextModeratorFromQueue()` : Récupérer le prochain modérateur en attente
-   `handleSimultaneousConnections($moderatorIds, $availableProfiles)` : Gérer les connexions simultanées
-   `preventDoubleInitiation($clientId, $profileId)` : Empêcher double approche client
-   `rebalanceModeratorLoad()` : Rééquilibrer la charge entre modérateurs
-   `resolveAttributionConflicts()` : Résoudre les conflits d'attribution
-   `validateUniqueClientProfileAssignment($clientId, $profileId)` : Valider unicité client-profil

### `app/Services/ModeratorActivityService.php`

**Méthodes à modifier :**

-   `recordTypingActivity()` : Intégrer la surveillance d'inactivité pour réattribution

**Nouvelles méthodes à ajouter :**

-   `detectInactiveModerators($thresholdMinutes = 1)` : Détecter les modérateurs inactifs (CHANGER : Surveillance toutes les 15 secondes au lieu de 30)
-   `triggerReassignmentForInactivity($moderatorId)` : Déclencher réattribution pour inactivité
-   `calculateModeratorWorkload($moderatorId)` : Calculer la charge de travail
-   `monitorResponseTimes()` : Surveiller les temps de réponse par client
-   `identifyOverloadedModerators()` : Identifier les modérateurs surchargés

## 2. Nouveaux fichiers à créer

### `app/Services/ModeratorQueueService.php`

**Objectif** : Gérer la file d'attente des modérateurs sans attribution

**Méthodes principales :**

-   `addToQueue($moderatorId, $priority = 0)` : Ajouter à la file d'attente
-   `removeFromQueue($moderatorId)` : Retirer de la file d'attente
-   `getQueuePosition($moderatorId)` : Obtenir position dans la file
-   `processQueue()` : Traiter la file d'attente pour attribution (Vérification automatique quand un profil se libère, Attribution immédiate au premier en file d'attente)
-   `reorderQueue()` : Réorganiser selon priorités
-   `getQueueStatus()` : État actuel de la file d'attente

### `app/Services/ProfileLockService.php`

**Objectif** : Gérer les verrous temporaires des profils et clients

**Méthodes principales :**

-   `lockProfile($profileId, $moderatorId, $duration)` : Verrouiller un profil
-   `unlockProfile($profileId)` : Déverrouiller un profil
-   `isProfileLocked($profileId)` : Vérifier verrou profil
-   `lockClient($clientId, $profileId, $moderatorId, $duration)` : Verrouiller un client
-   `unlockClient($clientId, $profileId)` : Déverrouiller un client
-   `isClientLocked($clientId, $profileId)` : Vérifier verrou client
-   `cleanExpiredLocks()` : Nettoyer les verrous expirés
-   `getLockInfo($resource, $type)` : Informations sur un verrou

### `app/Services/ConflictResolutionService.php`

**Objectif** : Résoudre les conflits d'attribution simultanée

**Méthodes principales :**

-   `handleConnectionCollision($moderatorIds)` : Gérer collision de connexions
-   `prioritizeByTimestamp($moderatorIds)` : Prioriser par horodatage
-   `prioritizeById($moderatorIds)` : Prioriser par ID modérateur
-   `logConflict($conflictData)` : Logger les conflits pour monitoring
-   `validateAssignmentIntegrity()` : Valider l'intégrité des attributions
-

### `app/Services/LoadBalancingService.php`

**Objectif** : Équilibrer la charge entre modérateurs

**Méthodes principales :**

-   `calculateModeratorScores()` : Calculer scores de disponibilité
-   `detectImbalance()` : Détecter déséquilibres de charge (Seuil de déséquilibre = 2 clients de différence, Délai anti-ping-pong = 5 minutes minimum entre réattributions)
-   `redistributeClients()` : Redistribuer les clients
-   `getOptimalAssignment($clientId, $profileId)` : Obtenir attribution optimale
-   `rebalanceActiveAssignments()` : Rééquilibrer les attributions actives
-   `generateLoadReport()` : Générer rapport de charge

### `app/Tasks/ProfileAssignmentMonitoringTask.php`

**Objectif** : Surveillance continue du système d'attribution

**Méthodes principales :**

-   `handle()` : Traitement principal de surveillance (AJOUTER : $this->detectOrphanProfiles(), AJOUTER : $this->cleanInconsistentAssignments();)
-   `checkForConflicts()` : Vérifier les conflits d'attribution
-   `monitorQueueStatus()` : Surveiller l'état de la file d'attente
-   `validateAssignmentRules()` : Valider le respect des règles
-   `cleanupExpiredStates()` : Nettoyer les états expirés
-   `generateAlerts()` : Générer des alertes système

### `app/Events/ModeratorQueuePositionChanged.php`

**Objectif** : Événement de changement de position dans la file d'attente

-   **Canaux** : `private-moderator.{moderator_id}`
-   **Données** : Position, temps d'attente estimé, profils disponibles

### `app/Events/ProfileLockStatusChanged.php`

**Objectif** : Événement de changement de statut de verrou

-   **Canaux** : `private-profile.{profile_id}`
-   **Données** : Type de verrou, durée, modérateur concerné

## 3. Modifications du frontend

### `resources/js/stores/moderatorStore.js`

**Méthodes à modifier :**

-   `initialize()` : Intégrer gestion de la file d'attente
-   `setupModeratorWebSocketListeners()` : Ajouter écouteurs pour nouveaux événements
-   `loadAssignedProfiles()` : Gérer les profils verrouillés et la file d'attente

**Nouvelles méthodes à ajouter :**

-   `handleQueuePosition(event)` : Gérer changement de position en file d'attente
-   `handleProfileLockStatus(event)` : Gérer statut de verrou profil
-   `requestProfileUnlock()` : Demander déverrouillage profil
-   `showQueueStatus()` : Afficher statut de la file d'attente
-   `handleConflictResolution(event)` : Gérer résolution de conflits

### `resources/js/Client/Pages/Moderator.vue`

**Modifications nécessaires :**

-   Ajouter interface de file d'attente
-   Afficher indicateurs de verrous
-   Montrer position dans la file d'attente
-   Interface pour gestion des conflits
-   Alertes de réattribution forcée

## 4. Nouvelles migrations

### `database/migrations/xxx_add_locking_system_to_moderator_profile_assignments.php`

**Colonnes à ajouter :**

-   `locked_clients` (JSON) : Clients verrouillés avec timestamps
-   `queue_position` (INTEGER) : Position file d'attente
-   `assigned_at` (TIMESTAMP) : Horodatage attribution
-   `last_activity_check` (TIMESTAMP) : Dernière vérification activité

### `database/migrations/xxx_create_profile_locks_table.php`

**Nouvelle table pour gérer les verrous :**

-   `id`, `profile_id`, `moderator_id`, `locked_at`, `expires_at`, `lock_type`

### `database/migrations/xxx_create_client_locks_table.php`

**Nouvelle table pour verrous clients :**

-   `id`, `client_id`, `profile_id`, `moderator_id`, `locked_at`, `expires_at`, `lock_reason`

### `database/migrations/xxx_create_moderator_queue_table.php`

**Nouvelle table pour la file d'attente :**

-   `id`, `moderator_id`, `queued_at`, `priority`, `position`, `estimated_wait_time`

## 5. Nouveaux contrôleurs

### `app/Http/Controllers/Moderator/QueueController.php`

**Méthodes :**

-   `getQueueStatus()` : Statut de la file d'attente
-   `requestPriorityChange()` : Demander changement de priorité
-   `leaveQueue()` : Quitter la file d'attente

### `app/Http/Controllers/Moderator/LockController.php`

**Méthodes :**

-   `getLockStatus()` : Statut des verrous
-   `requestUnlock()` : Demander déverrouillage
-   `extendLock()` : Étendre durée de verrou

## 6. Routes supplémentaires

### `routes/web.php`

-   `/moderateur/queue/status`
-   `/moderateur/queue/leave`
-   `/moderateur/locks/status`
-   `/moderateur/locks/request-unlock`
-   `/moderateur/conflicts/resolve`

---

Cette architecture complète permettra de gérer tous les scénarios décrits avec une gestion robuste des conflits, des verrous, et de la file d'attente, tout en maintenant l'intégrité du système d'attribution unique client-profil.
