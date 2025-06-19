# Analyse Technique Approfondie du Système de Modération

## 1. Vue d'ensemble du système

Le système de modération de l'application est une infrastructure sophistiquée permettant à des modérateurs de gérer les communications entre des profils virtuels et des clients réels. Ce document explique en détail le fonctionnement technique de chaque composant de cette architecture.

### 1.1 Concept fondamental

Les modérateurs sont des utilisateurs spécialisés qui peuvent utiliser plusieurs profils virtuels simultanément pour interagir avec les clients de l'application. L'attribution des profils aux modérateurs est gérée par un algorithme intelligent qui équilibre la charge de travail et garantit une expérience utilisateur optimale.

### 1.2 Architecture globale

Le système repose sur plusieurs composants clés :

1. **Backend Laravel** : Gère la logique métier, l'authentification et les autorisations
2. **Frontend Vue.js** : Interface utilisateur réactive pour les modérateurs
3. **WebSockets** : Communication en temps réel via Pusher
4. **Pinia Store** : Gestion de l'état centralisée côté client
5. **Tâches planifiées** : Distribution automatique des messages et équilibrage de charge

## 2. Analyse des Composants Backend

### 2.1 Contrôleurs

#### `ModeratorController.php`

Ce contrôleur est le point d'entrée principal pour toutes les fonctionnalités de modération :

```php
namespace App\Http\Controllers\Moderator;

class ModeratorController extends Controller
{
    // ...
}
```

Méthodes principales :

-   **`index()`** : Affiche la page principale des modérateurs
-   **`getClients()`** : Récupère les clients attribués au modérateur avec leurs messages non répondus
-   **`getAssignedProfile()`** : Renvoie tous les profils attribués au modérateur ainsi que le profil principal
-   **`getMessages()`** : Récupère l'historique des messages avec un client spécifique
-   **`sendMessage()`** : Envoie un message à un client au nom d'un profil
-   **`getAvailableClients()`** : Liste les clients disponibles pour entamer une conversation
-   **`startConversation()`** : Démarre une nouvelle conversation avec un client
-   **`setPrimaryProfile()`** : Définit un profil comme profil principal du modérateur

Chaque méthode implémente des vérifications d'autorisation pour garantir que le modérateur a bien accès au profil concerné.

### 2.2 Services

#### `ModeratorAssignmentService.php`

Ce service gère l'attribution intelligente des profils aux modérateurs et l'équilibrage de la charge :

Méthodes clés :

-   **`assignProfileToModerator()`** : Attribue un profil à un modérateur
-   **`findLeastBusyModerator()`** : Détermine le modérateur le plus disponible pour une conversation
-   **`assignClientToModerator()`** : Attribue un client à un modérateur spécifique
-   **`updateLastActivity()`** : Met à jour l'horodatage de dernière activité d'un modérateur
-   **`processUnassignedMessages()`** : Traite automatiquement les messages clients non attribués
-   **`reassignInactiveProfiles()`** : Réattribue les profils des modérateurs inactifs

L'équilibrage de charge est basé sur un score de disponibilité calculé selon la formule :

```php
$score = 100 - ($activeConversations * 20) - ($unansweredMessages * 10);
```

#### `WebSocketHealthService.php`

Ce service surveille l'état des connexions WebSocket et assure leur bon fonctionnement :

-   **`checkConnectionStatus()`** : Vérifie l'état des connexions WebSocket
-   **`logConnectionStatus()`** : Enregistre des statistiques sur l'état des connexions
-   **`handleConnectionError()`** : Gère les erreurs de connexion

### 2.3 Événements

#### `MessageSent.php`

Déclenché lorsqu'un message est envoyé, que ce soit par un client ou un modérateur :

```php
class MessageSent implements ShouldBroadcastNow
{
    // ...

    public function broadcastOn(): array
    {
        // Canal privé pour le client
        $clientChannel = new PrivateChannel('client.' . $this->message->client_id);

        // Canal privé pour le profil (utilisé par les modérateurs)
        $profileChannel = new PrivateChannel('profile.' . $this->message->profile_id);

        return [$clientChannel, $profileChannel];
    }

    // ...
}
```

Cet événement diffuse sur deux canaux privés simultanément :

-   `client.{id}` - accessible uniquement par le client concerné
-   `profile.{id}` - accessible par les modérateurs ayant ce profil attribué

#### `ProfileAssigned.php`

Déclenché lorsqu'un profil est attribué à un modérateur :

```php
class ProfileAssigned implements ShouldBroadcast
{
    // ...

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }

    // ...
}
```

#### `ClientAssigned.php`

Déclenché lorsqu'un client est attribué à un modérateur :

```php
class ClientAssigned implements ShouldBroadcast
{
    // ...

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }

    // ...
}
```

### 2.4 Tâches planifiées

#### `ProcessUnassignedMessagesTask.php`

Cette tâche s'exécute périodiquement pour traiter les messages non assignés :

```php
class ProcessUnassignedMessagesTask
{
    // ...

    public function schedule(): string
    {
        return '*/30 * * * * *'; // Toutes les 30 secondes
    }

    public function __invoke(): void
    {
        // Libérer d'abord les profils des modérateurs inactifs
        $releasedCount = $this->assignmentService->reassignInactiveProfiles(10);

        // Traiter les messages non assignés
        $assignedCount = $this->assignmentService->processUnassignedMessages();
    }
}
```

#### `ProcessUrgentMessagesTask.php`

Cette tâche se concentre sur les messages urgents nécessitant une attention immédiate :

```php
class ProcessUrgentMessagesTask
{
    // ...

    public function schedule(): string
    {
        return '*/30 * * * * *'; // Toutes les 30 secondes
    }

    public function __invoke(): void
    {
        // Traiter seulement les messages urgents (non répondus depuis 2+ minutes)
        $assignedCount = $this->assignmentService->processUnassignedMessages(true);
    }
}
```

Ces tâches sont enregistrées dans le fichier `bootstrap/tasks.php` pour être exécutées automatiquement.

### 2.5 Routes et autorisations

#### `channels.php`

Ce fichier définit les règles d'authentification pour les canaux de diffusion WebSocket :

```php
// Canal privé pour les clients
Broadcast::channel('client.{id}', function ($user, $id) {
    return $user->isClient() && $user->id == $id;
});

// Canal privé pour les profils
Broadcast::channel('profile.{id}', function ($user, $id) {
    // Un modérateur peut accéder au canal d'un profil s'il lui est attribué
    if ($user->isModerator()) {
        return ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $id)
            ->where('is_active', true)
            ->exists();
    }
    return false;
});

// Canal privé pour les modérateurs
Broadcast::channel('moderator.{id}', function ($user, $id) {
    return $user->isModerator() && $user->id == $id;
});
```

Ces règles garantissent que :

-   Seul le client concerné peut accéder à son canal privé
-   Seuls les modérateurs avec attribution active peuvent accéder aux canaux des profils
-   Chaque modérateur n'a accès qu'à son propre canal de notifications

#### `web.php`

Ce fichier définit les routes HTTP pour l'interface de modération :

```php
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->group(function () {
    Route::get('/', [ModeratorController::class, 'index'])->name('moderator.dashboard');
    Route::get('/clients', [ModeratorController::class, 'getClients']);
    Route::get('/profile', [ModeratorController::class, 'getAssignedProfile']);
    Route::get('/messages', [ModeratorController::class, 'getMessages']);
    Route::post('/send-message', [ModeratorController::class, 'sendMessage']);
    Route::get('/available-clients', [ModeratorController::class, 'getAvailableClients']);
    Route::post('/start-conversation', [ModeratorController::class, 'startConversation']);
    Route::post('/set-primary-profile', [ModeratorController::class, 'setPrimaryProfile']);
    // ...
});
```

## 3. Analyse des Composants Frontend

### 3.1 Composant Vue principal

#### `Moderator.vue`

C'est le composant principal de l'interface modérateur :

```vue
<template>
    <div class="moderator-dashboard">
        <!-- Section Liste des Clients -->
        <div class="clients-list">
            <!-- ... -->
        </div>

        <!-- Section Chat -->
        <div class="chat-section">
            <!-- ... -->
        </div>

        <!-- Section Informations -->
        <div class="info-section">
            <!-- ... -->
        </div>
    </div>
</template>

<script setup>
// ...
</script>
```

Fonctionnalités principales :

-   Affichage des clients attribués et de leurs messages
-   Interface de chat avec historique des messages
-   Système de notification pour les nouveaux messages
-   Gestion des profils multiples

### 3.2 Store Pinia

#### `moderatorStore.js`

Ce store centralise la gestion des données et des interactions avec l'API :

```js
export const useModeratorStore = defineStore("moderator", {
    state: () => ({
        // État du modérateur
        moderatorId: null,
        moderatorName: null,

        // Profils attribués
        assignedProfiles: [],
        currentAssignedProfile: null,

        // Clients attribués
        assignedClients: [],
        availableClients: [],

        // Conversation actuelle
        selectedClient: null,

        // Messages
        messages: {},

        // Notifications
        notifications: [],

        // État de chargement et de connexion
        loading: false,
        webSocketStatus: "disconnected",
    }),

    actions: {
        // Initialisation du store
        async initialize() {
            /* ... */
        },

        // Chargement des données
        async loadModeratorData() {
            /* ... */
        },
        async loadAssignedProfiles() {
            /* ... */
        },
        async loadAssignedClients() {
            /* ... */
        },
        async loadMessages(clientId, page, append) {
            /* ... */
        },

        // Actions utilisateur
        async sendMessage(clientId, content, attachment) {
            /* ... */
        },
        async setPrimaryProfile(profileId) {
            /* ... */
        },
        async startConversation(clientId, profileId) {
            /* ... */
        },

        // Gestion des WebSockets
        setupWebSocketListeners() {
            /* ... */
        },
        handleNewMessage(data) {
            /* ... */
        },
    },
});
```

### 3.3 Services JavaScript

#### `WebSocketManager.js`

Ce service gère les connexions WebSocket côté client :

```js
class WebSocketManager {
    constructor() {
        this.connectionStatus = "disconnected";
        this.subscriptions = new Map();
        this.channels = new Map();
        this.listeners = new Map();
        // ...
    }

    async initialize() {
        /* ... */
    }

    subscribeToChannel(channelName, events) {
        /* ... */
    }

    unsubscribeFromChannel(channelName) {
        /* ... */
    }

    checkConnectionHealth() {
        /* ... */
    }

    // ...
}
```

#### `AuthenticationService.js`

Ce service gère l'authentification et les tokens CSRF :

```js
class AuthenticationService {
    constructor() {
        this.user = null;
        this.isAuthenticated = false;
        // ...
    }

    async initialize() {
        /* ... */
    }

    async refreshCSRFToken() {
        /* ... */
    }

    async getUserData() {
        /* ... */
    }

    // ...
}
```

### 3.4 Configuration WebSocket

#### `bootstrap.js`

Ce fichier initialise la connexion WebSocket via Pusher :

```js
// Configuration Echo avec Pusher
const echoConfig = {
    broadcaster: "pusher",
    key: "6ae46164b8889f3914b1",
    cluster: "eu",
    forceTLS: true,
    encrypted: true,
    auth: {
        headers: {
            "X-CSRF-TOKEN":
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content") || "",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
    },
    authEndpoint: "/broadcasting/auth",
};

window.Echo = new Echo(echoConfig);

// Signaler que Echo est initialisé
document.dispatchEvent(new CustomEvent("echo:initialized"));
```

## 4. Flux de Traitement des Messages

### 4.1 Traitement d'un message client

Lorsqu'un client envoie un message, le système suit ce flux :

1. **Réception du message** : Le message est stocké en base de données
2. **Événement `MessageSent`** : Diffuse le message sur les canaux appropriés
3. **Tâche `ProcessUnassignedMessagesTask`** : S'exécute périodiquement pour traiter les messages
4. **`ModeratorAssignmentService`** : Détermine le modérateur le plus approprié
5. **Événement `ClientAssigned`** : Notifie le modérateur sélectionné
6. **WebSocket** : Le modérateur reçoit la notification en temps réel
7. **Interface modérateur** : Affiche le nouveau message dans les notifications

### 4.2 Traitement des messages urgents

Pour les messages sans réponse depuis plus de 2 minutes :

1. **Tâche `ProcessUrgentMessagesTask`** : Identifie les messages urgents
2. **`ModeratorAssignmentService`** : Force la réattribution même si déjà assigné
3. **Événement `ClientAssigned`** : Notifie le nouveau modérateur
4. **Notification prioritaire** : Le message apparaît en haut des notifications

### 4.3 Réponse du modérateur

Lorsqu'un modérateur répond :

1. **Interface modérateur** : Envoi du message via le store Pinia
2. **API** : Le contrôleur `ModeratorController` vérifie l'autorisation
3. **Base de données** : Enregistrement du message
4. **Événement `MessageSent`** : Diffusion sur les canaux appropriés
5. **Client** : Réception du message en temps réel

## 5. Mécanismes d'Équilibrage de Charge

### 5.1 Critères d'attribution

Le système attribue les messages selon ces critères :

1. **Continuité des conversations** : Privilégie les modérateurs déjà impliqués
2. **Score de disponibilité** : `100 - (Conversations_actives × 20) - (Messages_en_attente × 10)`
3. **Classification des modérateurs** :
    - **Disponible** : Score > 50
    - **Occupé** : Score entre 20 et 50
    - **Surchargé** : Score < 20

### 5.2 Libération des ressources

-   Les profils des modérateurs inactifs depuis 10+ minutes sont libérés
-   Les profils qui n'ont plus de messages en attente sont libérés (sauf profil principal)
-   Les messages urgents sont redistribués pour garantir une réponse rapide

## 6. Sécurité et Confidentialité

### 6.1 Authentification des canaux

Le système utilise des canaux privés avec authentification :

```php
Broadcast::channel('profile.{profileId}', function ($user, $profileId) {
    // Un modérateur peut accéder au canal d'un profil s'il lui est attribué
    if ($user->isModerator()) {
        return ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();
    }
    return false;
});
```

### 6.2 Vérification des autorisations

Chaque action du contrôleur vérifie les autorisations :

```php
// Vérifier que ce modérateur a bien accès à ce profil
$hasAccess = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
    ->where('profile_id', $request->profile_id)
    ->where('is_active', true)
    ->exists();

if (!$hasAccess) {
    return response()->json([
        'error' => 'Accès non autorisé à ce profil'
    ], 403);
}
```

### 6.3 Protection CSRF

Le système implémente une protection CSRF robuste :

```javascript
// Configuration d'Axios avec CSRF token
const configureAxios = async () => {
    let token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (!token) {
        try {
            await axios.get("/sanctum/csrf-cookie");
            token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content");
        } catch (error) {
            console.error("Impossible de récupérer le token CSRF:", error);
        }
    }

    axios.defaults.headers.common["X-CSRF-TOKEN"] = token;
};
```

## Conclusion

Le système de modération implémente une architecture technique complète, sécurisée et performante. Les différents composants travaillent ensemble pour offrir une expérience fluide tant aux modérateurs qu'aux clients, avec une répartition intelligente de la charge de travail et une communication en temps réel efficace.

Cette architecture permet une gestion simultanée de multiples profils par modérateur, maximisant ainsi l'efficacité tout en garantissant que les clients reçoivent des réponses rapides et personnalisées.
