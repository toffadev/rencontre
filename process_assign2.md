# Processus d'Assignation des Profils et Clients

Ce document explique en détail le processus d'assignation des profils virtuels aux modérateurs et des clients aux profils dans l'application.

## 1. Structure de Données et Modèles Clés

### ModeratorProfileAssignment

Ce modèle est central dans le système d'assignation et contient les champs suivants:

-   `user_id`: ID du modérateur
-   `profile_id`: ID du profil virtuel
-   `is_active`: Si l'assignation est active
-   `is_primary`: Si c'est le profil principal du modérateur
-   `is_exclusive`: Si le profil est exclusif à ce modérateur
-   `is_currently_active`: Si le modérateur est actuellement en train d'interagir avec ce profil
-   `last_activity`: Dernière activité sur ce profil
-   `last_message_sent`: Dernier message envoyé
-   `last_typing`: Dernière activité de frappe
-   `conversation_ids`: Tableau JSON des IDs de clients assignés à ce modérateur pour ce profil
-   `active_conversations_count`: Nombre de conversations actives
-   `priority_score`: Score de priorité pour l'attribution

## 2. Processus d'Assignation des Profils aux Modérateurs

### Connexion du Modérateur

1. **Frontend - Chargement de la page Moderator.vue**

    - L'interface affiche un écran de chargement pendant l'initialisation
    - Le composant établit une connexion WebSocket sécurisée avec le serveur
    - Les données du modérateur sont synchronisées entre le frontend et le backend

2. **Frontend - Initialisation du Store Moderator**

    - Méthode: `initialize()` dans `moderatorStore.js`
    - Cette méthode est appelée lors du montage du composant Moderator.vue
    - Elle charge les données du modérateur, les profils assignés et configure les écouteurs WebSocket

3. **Frontend - Chargement des Profils Assignés**

    - Méthode: `loadAssignedProfiles()` dans `moderatorStore.js`
    - Route appelée: `GET /moderateur/profile`
    - Récupère tous les profils assignés au modérateur et identifie le profil principal

4. **Backend - Récupération des Profils Assignés**

    - Contrôleur: `ModeratorController@getAssignedProfile`
    - Si aucun profil principal n'est défini mais des profils sont assignés, le système en définit un comme principal
    - Si aucun profil n'est assigné, le système en attribue un automatiquement via `assignProfileToModerator()`

5. **Backend - Service d'Attribution**

    - Service: `ModeratorAssignmentService`
    - Méthode: `assignProfileToModerator($moderatorId, $profileId = null, $isPrimary = false)`
    - Cette méthode vérifie:
        - Si le profil existe
        - Si le modérateur existe
        - Si le modérateur a déjà ce profil assigné
        - Si d'autres modérateurs ont déjà ce profil assigné
        - Le nombre de clients ayant des messages en attente pour ce profil

6. **Backend - Événement d'Attribution de Profil**

    - Événement: `ProfileAssigned`
    - Canaux: `private-moderator.{moderator_id}`
    - Contient des informations sur le profil assigné et si ce profil est partagé avec d'autres modérateurs

7. **Frontend - Réception de l'Événement d'Attribution**
    - Méthode: `setupModeratorWebSocketListeners()` dans `moderatorStore.js`
    - Gère la transition entre profils avec un compte à rebours de 3 secondes
    - Permet au modérateur de demander un délai supplémentaire de 5 minutes avant le changement de profil

### Rotation Automatique des Profils

1. **Tâche Planifiée - RotateModeratorProfilesTask**

    - Identifie les profils avec des messages en attente
    - Identifie les modérateurs inactifs sur leurs profils actuels (inactivité > 1 minute)
    - Réassigne les profils avec messages en attente aux modérateurs inactifs

2. **Service - ModeratorAssignmentService**
    - Méthode: `reassignInactiveProfiles(int $inactiveMinutes = 10)`
    - Désactive les assignations inactives pour permettre la réassignation
    - Méthode: `checkInactiveAssignments()`
    - Vérifie les modérateurs inactifs (20 minutes) et réassigne leurs profils si nécessaire

## 3. Processus d'Assignation des Clients aux Modérateurs

### Chargement des Clients Assignés

1. **Frontend - Affichage des Clients Assignés**

    - Les clients sont affichés dans l'interface sous forme de cartes
    - L'interface distingue les clients assignés et les clients disponibles

2. **Frontend - Chargement des Clients dans le Store**

    - Méthode: `loadAssignedClients()` dans `moderatorStore.js`
    - Route appelée: `GET /moderateur/clients`

3. **Backend - Récupération des Clients Assignés**

    - Contrôleur: `ModeratorController@getClients`
    - Récupère uniquement le profil principal actif du modérateur
    - Ne récupère que les clients présents dans le tableau `conversation_ids` de ce profil principal
    - Retourne les informations détaillées sur chaque client (messages non lus, dernier message, etc.)

4. **Backend - Processus d'Attribution des Clients**

    - Service: `ModeratorAssignmentService`
    - Méthode: `processUnassignedMessages($urgentOnly = false)`
    - Identifie les clients nécessitant une réponse (messages non répondus)
    - Considère comme urgents les messages sans réponse depuis plus de 2 minutes
    - Assigne chaque client à un modérateur approprié

5. **Backend - Attribution d'un Client à un Modérateur**

    - Service: `ModeratorAssignmentService`
    - Méthode: `assignClientToModerator($clientId, $profileId)`
    - Vérifie si le client est déjà assigné à un modérateur pour ce profil
    - Si non, trouve le modérateur le plus disponible pour ce profil en utilisant un système de score
    - Ajoute le client au tableau `conversation_ids` de l'assignation

6. **Backend - Ajout du Client à l'Attribution**

    - Modèle: `ModeratorProfileAssignment`
    - Méthode: `addConversation($clientId)`
    - Met à jour le tableau JSON `conversation_ids` et le compteur `active_conversations_count`

7. **Backend - Événement d'Attribution de Client**

    - Événement: `ClientAssigned`
    - Canaux: `private-moderator.{moderator_id}`
    - Contient des informations sur le client assigné et si le profil est partagé

8. **Frontend - Réception de l'Événement d'Attribution de Client**
    - Méthode: `setupModeratorWebSocketListeners()` dans `moderatorStore.js`
    - Recharge la liste des clients assignés
    - Sélectionne automatiquement le nouveau client si aucun client n'est actuellement sélectionné

## 4. Gestion des Activités et Indicateurs de Frappe

1. **Frontend - Détection de Frappe**

    - Un système de surveillance de l'input de message détecte quand un modérateur est en train de taper
    - Utilise un mécanisme de debounce pour éviter d'envoyer trop d'événements (minimum 500ms entre événements)

2. **Backend - Enregistrement de l'Activité**

    - Service: `ModeratorActivityService`
    - Méthode: `recordTypingActivity($userId, $profileId, $clientId)`
    - Met à jour le timestamp `last_typing` de l'assignation
    - Émet l'événement `ModeratorActivityEvent` seulement si nécessaire (pas d'événement si moins de 3 secondes depuis le dernier)

3. **Backend - Événement d'Activité**

    - Événement: `ModeratorActivityEvent`
    - Canaux: `private-profile.{profile_id}`
    - Permet aux autres modérateurs partageant le même profil de voir l'activité

4. **Frontend - Affichage des Indicateurs d'Activité**
    - Affiche les indicateurs "est en train d'écrire..." pour les clients
    - Affiche les activités des autres modérateurs sur le même profil (typing, reading, etc.)
    - Nettoie automatiquement les activités anciennes (plus de 5 minutes)

## 5. Envoi et Réception de Messages

1. **Frontend - Envoi de Message**

    - Méthode: `sendMessage()` dans `Moderator.vue`
    - Appelle la méthode `sendMessage()` du store qui crée d'abord un message temporaire
    - Met à jour l'activité de dernière réponse via `updateLastMessageActivity()`

2. **Backend - Traitement du Message**

    - Contrôleur: `ModeratorController@sendMessage`
    - Vérifie l'accès du modérateur au profil
    - Crée le message et gère les pièces jointes
    - Utilise `event()` pour diffusion immédiate

3. **Backend - Événement de Message**

    - Événement: `MessageSent` implémente `ShouldBroadcast`
    - Canaux: `private-client.{client_id}` et `private-profile.{profile_id}`
    - Assure la diffusion du message aux clients et autres modérateurs

4. **Frontend - Réception du Message**
    - Les écouteurs WebSocket reçoivent le message et mettent à jour l'interface
    - Les messages sont ajoutés à la conversation en cours
    - Le message temporaire est remplacé par le message réel retourné par le serveur

## 6. Partage de Profils Entre Modérateurs

1. **Détection de Profil Partagé**

    - Lors de l'attribution d'un profil, le système vérifie si d'autres modérateurs ont déjà ce profil
    - Si un seul client a des messages en attente, le système évite d'attribuer le profil à plusieurs modérateurs
    - Les événements `ProfileAssigned` et `ClientAssigned` incluent un indicateur `isShared` ou `isSharedProfile`

2. **Affichage dans l'Interface**

    - L'interface affiche un indicateur lorsqu'un profil est partagé
    - Les modérateurs peuvent voir les activités des autres modérateurs sur le même profil

3. **Coordination des Activités**
    - Les événements `ModeratorActivityEvent` permettent aux modérateurs de voir qui est actif sur quel client
    - Le système stocke les activités des autres modérateurs dans `activeModeratorsByProfile` dans le store
    - Nettoie automatiquement les activités anciennes de plus de 5 minutes

## 7. Algorithme de Sélection des Modérateurs

1. **Calcul du Score de Disponibilité**

    - Méthode: `findLeastBusyModerator($clientId, $profileId)` dans `ModeratorAssignmentService`
    - Formule: `Score = 100 - (Conversations_actives × 20) - (Messages_en_attente × 10)`
    - Catégorisation des modérateurs:
        - Disponible: Score > 50
        - Occupé: Score entre 20 et 50
        - Surchargé: Score < 20

2. **Règles de Sélection**

    - Si une conversation existe déjà entre le client et le profil, et que le modérateur actuel a un score > 30, conserver le même modérateur
    - Sinon, sélectionner d'abord parmi les modérateurs disponibles
    - Si aucun modérateur disponible, sélectionner parmi les modérateurs occupés
    - En dernier recours, sélectionner même parmi les modérateurs surchargés

3. **Optimisation de la Charge**
    - Le système libère automatiquement les profils qui ont reçu une réponse via `releaseRespondedProfiles()`
    - Les modérateurs peuvent demander un délai supplémentaire avant un changement de profil via `requestDelay()`

## 8. Schéma des Tables de Base de Données Impliquées

-   `users` - Stocke les utilisateurs (modérateurs, clients, etc.)
-   `profiles` - Stocke les profils virtuels
-   `moderator_profile_assignments` - Associe les profils aux modérateurs
    -   `user_id` - ID du modérateur
    -   `profile_id` - ID du profil
    -   `is_active` - Si l'attribution est active
    -   `is_primary` - Si c'est le profil principal du modérateur
    -   `is_exclusive` - Si le profil est exclusif à ce modérateur
    -   `is_currently_active` - Si le modérateur interagit actuellement avec ce profil
    -   `conversation_ids` - Tableau JSON des IDs de clients attribués
    -   `active_conversations_count` - Nombre de conversations actives
    -   `priority_score` - Score de priorité pour l'attribution
    -   `last_activity` - Dernière activité sur ce profil
    -   `last_message_sent` - Dernier message envoyé
    -   `last_typing` - Dernière activité de frappe
-   `messages` - Stocke les messages échangés
    -   `client_id` - ID du client
    -   `profile_id` - ID du profil
    -   `content` - Contenu du message
    -   `is_from_client` - Si le message vient du client
    -   `read_at` - Quand le message a été lu

Table users :
Schema::create('users', function (Blueprint $table) {
$table->id();
$table->enum('type', ['client', 'moderateur', 'admin'])->default('client');
$table->string('name');
$table->string('email')->unique();
$table->timestamp('email_verified_at')->nullable();
$table->string('password');
$table->integer('points')->default(0);
$table->enum('status', ['active', 'inactive', 'banned'])->default('active');
$table->rememberToken();
$table->timestamps();
});
Schema::table('users', function (Blueprint $table) {
$table->timestamp('last_activity_at')->nullable();
});
Schema::table('users', function (Blueprint $table) {
$table->boolean('is_online')->default(false);
$table->timestamp('last_online_at')->nullable();
});

Voici la liste des chemins de tous les fichiers concernés par le processus d'assignation des profils et clients:
app/Models/ModeratorProfileAssignment.php - Modèle central pour l'assignation des profils
app/Http/Controllers/Moderator/ModeratorController.php - Contrôleur principal des modérateurs
app/Services/ModeratorAssignmentService.php - Service gérant l'assignation des profils et clients
app/Services/ModeratorActivityService.php - Service pour suivre l'activité des modérateurs
app/Events/ProfileAssigned.php - Événement émis lors de l'assignation d'un profil
app/Events/ClientAssigned.php - Événement émis lors de l'assignation d'un client
app/Events/ModeratorActivityEvent.php - Événement pour diffuser l'activité des modérateurs
app/Tasks/RotateModeratorProfilesTask.php - Tâche planifiée pour la rotation des profils
resources/js/Client/Pages/Moderator.vue - Interface principale des modérateurs
resources/js/stores/moderatorStore.js - Store Pinia pour la gestion de l'état des modérateurs
routes/channels.php - Configuration des canaux de diffusion WebSocket
app/Services/WebSocketHealthService.php - Service pour surveiller la santé des connexions WebSocket
database/migrations/2025_05_21_215800_create_moderator_profile_assignments_table.php - Migration pour la table des assignations
database/migrations/2025_06_22_071313_add_columns_to_moderator_profile_assignments_table.php - Migration pour ajouter des colonnes à la table
Ces fichiers constituent l'ensemble du système d'assignation des profils aux modérateurs et des clients aux profils dans l'application.
