# Documentation du Système de Modération

## 1. Introduction et Vue d'ensemble

Ce document explique le système de modération mis en place dans l'application de rencontres. Le concept principal est le suivant :

-   Les **modérateurs** sont des utilisateurs spécialisés qui peuvent utiliser des **profils virtuels** pour discuter avec les **clients** de l'application.
-   Les clients ne voient que le profil virtuel et ignorent qu'ils communiquent avec un modérateur.
-   **Chaque modérateur peut gérer PLUSIEURS profils virtuels simultanément** pour répondre efficacement aux messages des clients.
-   **Un profil virtuel peut être attribué à plusieurs modérateurs**, mais le système distribue équitablement les conversations pour éviter les doublons.
-   **Le système attribue automatiquement les profils aux modérateurs disponibles** en fonction de leur activité et de leur charge de travail.
-   **L'équilibrage de charge intelligent favorise le modérateur avec le moins de conversations en cours** pour garantir un temps de réponse optimal aux clients.
-   **Lorsqu'un client envoie un message, le système l'attribue automatiquement au modérateur le plus disponible** tout en préservant la continuité des conversations existantes.
-   Le système utilise des **événements en temps réel** pour notifier les modérateurs des nouveaux messages sans délai.
-   Des **mécanismes de sécurité** garantissent qu'un modérateur ne peut accéder qu'aux conversations liées aux profils qui lui sont attribués.

Cette architecture permet de maximiser l'efficacité des modérateurs tout en offrant une expérience personnalisée aux clients.

## 2. Structure de la Base de Données

### 2.1 Modèles et Tables

Nous avons créé deux nouveaux modèles principaux :

#### `ModeratorProfileAssignment`

-   Enregistre l'attribution d'un profil à un modérateur.
-   Table: `moderator_profile_assignments`
-   Champs:
    -   `user_id`: ID du modérateur
    -   `profile_id`: ID du profil virtuel
    -   `is_active`: Indique si l'attribution est active
    -   `is_primary`: **Indique si ce profil est le profil principal du modérateur**
    -   `is_exclusive`: **Indique si ce profil est attribué exclusivement à ce modérateur**
    -   `last_activity`: Horodatage de la dernière activité du modérateur

#### `Message`

-   Stocke les messages échangés entre les profils virtuels et les clients.
-   Table: `messages`
-   Champs:
    -   `client_id`: ID du client
    -   `profile_id`: ID du profil virtuel
    -   `moderator_id`: ID du modérateur qui a envoyé le message
    -   `content`: Contenu du message
    -   `is_from_client`: Indique si le message vient du client
    -   `read_at`: Horodatage de lecture du message

### 2.2 Migrations

Nous avons modifié la migration pour la table `moderator_profile_assignments` :

```php
Schema::create('moderator_profile_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('profile_id')->constrained()->onDelete('cascade');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_primary')->default(false); // Profil principal du modérateur
    $table->boolean('is_exclusive')->default(false); // Attribution exclusive
    $table->timestamp('last_activity')->nullable();
    $table->timestamps();

    // Un modérateur ne peut avoir qu'un seul profil principal actif à la fois
    $table->unique(['user_id', 'is_primary', 'is_active'], 'moderator_active_primary_profile');
});
```

**Points importants à noter :**

-   La contrainte d'unicité sur `user_id`, `is_primary` et `is_active` garantit qu'un modérateur ne peut avoir qu'un seul profil principal actif à la fois.
-   Le champ `is_primary` identifie le profil principal d'un modérateur (utilisé par défaut dans l'interface).
-   Le champ `is_exclusive` permet d'attribuer un profil à un seul modérateur si nécessaire.
-   Un modérateur peut maintenant avoir plusieurs attributions actives simultanément.

## 3. Le Service d'Attribution de Profils et Clients

Nous avons considérablement amélioré le service (`ModeratorAssignmentService`) pour gérer l'attribution équilibrée des profils et des clients aux modérateurs :

### 3.1 Méthodes principales

#### `assignProfileToModerator(User $moderator, ?Profile $profile = null, $makePrimary = true)`

-   Attribue un profil à un modérateur.
-   Si aucun profil n'est spécifié, le système en sélectionne un automatiquement.
-   Le paramètre `$makePrimary` permet de définir si ce profil doit être le profil principal du modérateur.
-   Déclenche l'événement `ProfileAssigned`.

#### `getAllAssignedProfiles(User $moderator)`

-   **NOUVEAU** : Récupère tous les profils actuellement attribués à un modérateur.
-   Permet à l'interface utilisateur d'afficher les multiples profils disponibles pour le modérateur.

#### `findLeastBusyModerator($clientId, $profileId)`

-   **NOUVEAU** : Trouve le modérateur avec la charge de travail la plus faible pour gérer un nouveau message client.
-   Prend en compte le nombre de conversations actives pour chaque modérateur.
-   Utilise les priorités suivantes :
    1. Les modérateurs qui ont déjà ce profil attribué et sans conversations
    2. Les modérateurs qui ont déjà ce profil attribué avec la plus faible charge
    3. N'importe quel modérateur sans conversation en cours
    4. Le modérateur avec la plus faible charge de travail

#### `assignClientToModerator($clientId, $profileId)`

-   **NOUVEAU** : Attribue un client à un modérateur spécifique selon la charge de travail.
-   Si le modérateur n'a pas encore le profil attribué, le système le lui attribue automatiquement.
-   Déclenche l'événement `ClientAssigned` pour notifier le modérateur.

#### `updateLastActivity(User $moderator, $profileId = null)`

-   Mise à jour pour accepter un ID de profil spécifique.
-   Permet de mettre à jour l'activité sur un profil particulier ou tous les profils.

#### `getClientsNeedingResponse()`

-   **NOUVEAU** : Récupère tous les clients qui attendent une réponse, ordonnés par priorité.
-   Se concentre sur les messages les plus anciens pour garantir que tous les clients reçoivent une réponse.

#### `processUnassignedMessages()`

-   **NOUVEAU** : Traite tous les messages clients non attribués et les assigner automatiquement aux modérateurs selon la charge de travail.
-   Exécuté régulièrement pour s'assurer qu'aucun client n'est laissé sans réponse.

## 4. Contrôleur pour les Modérateurs

Le `ModeratorController` a été considérablement amélioré pour prendre en charge les multiples profils et l'équilibrage de charge :

### 4.1 Routes

Nous avons ajouté une nouvelle route dans `routes/web.php` :

```php
Route::post('/set-primary-profile', [ModeratorController::class, 'setPrimaryProfile'])->name('set-primary-profile');
```

### 4.2 Méthodes du contrôleur

#### `index()`

-   Vérifie maintenant si le modérateur a des profils attribués (au pluriel).
-   Exécute `processUnassignedMessages()` pour distribuer les messages non attribués aux modérateurs disponibles.

#### `getClients()`

-   Complètement redessiné pour récupérer les clients associés à TOUS les profils attribués au modérateur.
-   Renvoie pour chaque client des informations sur le profil concerné par la conversation.
-   Les clients sont triés par ordre chronologique, les plus anciens messages apparaissant en premier.

#### `getAssignedProfile()`

-   Renommé mais conservé pour la compatibilité avec le frontend.
-   Renvoie maintenant la liste de tous les profils attribués ainsi que le profil principal.

#### `getMessages(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique dans la requête.
-   Vérifie que le modérateur a bien accès au profil demandé.

#### `sendMessage(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique dans la requête.
-   Vérifie que le modérateur a bien accès au profil demandé.
-   Met à jour l'activité uniquement pour ce profil spécifique.

#### `getAvailableClients()`

-   Amélioré pour prendre en compte tous les profils attribués au modérateur.
-   Pour chaque client, indique l'historique des conversations avec les différents profils du modérateur.

#### `startConversation(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique.
-   Si le modérateur n'a pas accès à ce profil, tente de le lui attribuer automatiquement.

#### `setPrimaryProfile(Request $request)` **(NOUVEAU)**

-   Permet à un modérateur de définir l'un de ses profils attribués comme profil principal.
-   Le profil principal est utilisé par défaut dans l'interface utilisateur.

## 5. Système d'Événements en Temps Réel

Le système utilise Laravel Echo avec Pusher pour garantir une communication en temps réel fluide entre le serveur et les interfaces des modérateurs. Cette architecture événementielle joue un rôle crucial dans l'efficacité du système de modération.

### 5.1 Événement `MessageSent`

Cet événement est déclenché chaque fois qu'un message est envoyé, que ce soit par un client ou par un modérateur. Son implémentation précise est définie dans le fichier `MessageSent.php` :

```php
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        // Canal privé pour le client
        $clientChannel = new PrivateChannel('client.' . $this->message->client_id);

        // Canal privé pour le profil (utilisé par les modérateurs)
        $profileChannel = new PrivateChannel('profile.' . $this->message->profile_id);

        return [$clientChannel, $profileChannel];
    }

    public function broadcastAs()
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'client_id' => $this->message->client_id,
            'profile_id' => $this->message->profile_id,
            'is_from_client' => $this->message->is_from_client,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
```

Points importants :

-   Utilisation de `ShouldBroadcastNow` pour une diffusion immédiate sans délai
-   Diffusion simultanée sur deux canaux privés distincts
-   Utilisation de canaux nommés de manière cohérente (`client.{id}` et `profile.{id}`)
-   Données optimisées pour minimiser la charge réseau tout en fournissant les informations essentielles

### 5.2 Événement `ProfileAssigned`

Cet événement est déclenché lorsqu'un profil est attribué à un modérateur :

```php
class ProfileAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderator;
    public $profile;
    public $isPrimary;
    public $clientId;

    public function __construct(User $moderator, Profile $profile, bool $isPrimary = false, ?int $clientId = null)
    {
        $this->moderator = $moderator;
        $this->profile = $profile;
        $this->isPrimary = $isPrimary;
        $this->clientId = $clientId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'profile.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'profile' => $this->profile,
            'is_primary' => $this->isPrimary,
            'client_id' => $this->clientId,
        ];
    }
}
```

Caractéristiques clés :

-   L'événement inclut des informations sur le statut du profil (principal ou non)
-   Il inclut optionnellement un ID de client si l'attribution est liée à une conversation spécifique
-   Il est diffusé uniquement au modérateur concerné via son canal privé

### 5.3 Événement `ClientAssigned`

Cet événement notifie un modérateur qu'un client lui a été attribué pour une conversation :

```php
class ClientAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderator;
    public $client;
    public $profile;

    public function __construct(User $moderator, User $client, Profile $profile)
    {
        $this->moderator = $moderator;
        $this->client = $client;
        $this->profile = $profile;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'client.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ],
            'profile' => [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
            ]
        ];
    }
}
```

Points notables :

-   L'événement transmet les informations essentielles sur le client et le profil concerné
-   Les données transmises sont filtrées pour ne contenir que les informations nécessaires
-   Comme pour `ProfileAssigned`, il est diffusé uniquement au modérateur concerné

### 5.4 Gestion des Notifications

La gestion des notifications dans le composant Moderator.vue est particulièrement élaborée :

```javascript
// Fonction d'ajout de notification
const addNotification = (message, clientId, clientName) => {
    const notification = {
        id: Date.now(),
        message,
        clientId,
        clientName,
        timestamp: new Date(),
        read: false,
    };
    notifications.value.unshift(notification);
    // Limiter à 50 notifications maximum
    if (notifications.value.length > 50) {
        notifications.value = notifications.value.slice(0, 50);
    }
};
```

Dans la fonction d'écoute des messages du profil :

```javascript
window.Echo.private(`profile.${profileId}`).listen(
    ".message.sent",
    async (data) => {
        if (data.is_from_client) {
            const clientId = data.client_id;

            // Vérifier si le message n'existe pas déjà
            if (
                chatMessages.value[clientId]?.some((msg) => msg.id === data.id)
            ) {
                return;
            }

            // Ajouter la notification
            const clientName =
                assignedClient.value.find((c) => c.id === clientId)?.name ||
                "Client";
            addNotification(data.content, clientId, clientName);

            // Autres traitements...
        }
    }
);
```

Cette implémentation offre plusieurs avantages :

-   Les notifications s'accumulent chronologiquement sans s'écraser
-   Le système vérifie si un message existe déjà avant de créer une notification
-   Les notifications conservent un état (lu/non lu)
-   Le système limite automatiquement le nombre de notifications pour éviter la surcharge
-   Les modérateurs peuvent facilement naviguer parmi leurs notifications

### 5.5 Configuration de l'Authentification des Canaux

La sécurité des canaux est assurée par une configuration rigoureuse des routes d'authentification Pusher/Laravel Echo :

```php
// Dans routes/channels.php
Broadcast::channel('client.{id}', function ($user, $id) {
    // Un client ne peut accéder qu'à son propre canal
    return $user->isClient() && $user->id == $id;
});

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

Broadcast::channel('moderator.{id}', function ($user, $id) {
    // Un utilisateur ne peut accéder qu'à son propre canal de modérateur
    return $user->isModerator() && $user->id == $id;
});
```

Cette configuration garantit que :

-   Seul le client concerné peut accéder à son canal privé
-   Seuls les modérateurs avec attribution active peuvent accéder aux canaux des profils
-   Chaque modérateur n'a accès qu'à son propre canal de notifications

### 5.6 Initialisation des Écouteurs d'Événements

Dans le composant Moderator.vue, l'initialisation des écouteurs se fait de manière robuste :

```javascript
onMounted(async () => {
    try {
        // [...configuration initiale...]

        // Configurer Laravel Echo
        if (window.Echo) {
            console.log(
                "Configuration de Laravel Echo pour recevoir les notifications en temps réel"
            );

            // Récupérer l'ID du modérateur depuis l'API
            const userResponse = await axios.get("/api/user");
            const moderatorId = userResponse.data.id;

            if (!moderatorId) {
                console.error("ID du modérateur non disponible");
                return;
            }

            console.log(`ID du modérateur connecté: ${moderatorId}`);

            // Écouter les notifications d'attribution de profil
            window.Echo.private(`moderator.${moderatorId}`)
                .listen(".profile.assigned", async (data) => {
                    console.log("Événement profile.assigned reçu:", data);
                    // Recharger les données après l'attribution d'un profil
                    await loadAssignedData();
                    // [...suite du traitement...]
                })
                .listen(".client.assigned", async (data) => {
                    console.log("Événement client.assigned reçu:", data);
                    // Recharger les données après l'attribution d'un client
                    await loadAssignedData();
                    // [...suite du traitement...]
                });

            // Si un profil est déjà attribué, écouter les messages sur son canal
            if (currentAssignedProfile.value) {
                listenToProfileMessages(currentAssignedProfile.value.id);
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'initialisation:", error);
    }
});
```

Ce code établit :

-   Une initialisation asynchrone sécurisée
-   Une vérification de disponibilité de Laravel Echo
-   Une récupération dynamique de l'identifiant du modérateur
-   Des écouteurs pour les différents types d'événements
-   Une gestion appropriée des erreurs avec journalisation

Ce système d'événements en temps réel constitue la colonne vertébrale du système de modération, permettant une réactivité immédiate et une expérience fluide tant pour les modérateurs que pour les clients.

## 6. Équilibrage de Charge des Messages

L'équilibrage intelligent des messages clients entre les modérateurs est l'un des points forts du système. Son implémentation repose sur des algorithmes qui analysent la charge de travail en temps réel et des critères de priorité clairement définis.

### 6.1 Critères d'attribution

Le système utilise les critères suivants (par ordre de priorité) pour attribuer les messages clients aux modérateurs appropriés :

1. **Continuité des conversations** : Le système privilégie fortement les modérateurs qui ont déjà le profil concerné et qui ont participé à la conversation récemment. Cette continuité est essentielle pour maintenir la cohérence des échanges.

2. **Charge de travail actuelle** : Pour les modérateurs ayant le même profil attribué, le système calcule précisément le nombre de clients en attente de réponse. Ce calcul se base sur :

    ```php
    // Calculer le nombre de conversations sans réponse dans les 30 dernières minutes
    $unansweredConversations = DB::table('messages as m1')
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
        ->where('m1.created_at', '>', now()->subMinutes(30))
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('messages as m3')
                ->whereRaw('m3.client_id = m1.client_id')
                ->whereRaw('m3.profile_id = m1.profile_id')
                ->where('m3.is_from_client', false)
                ->whereRaw('m3.created_at > m1.created_at');
        })
        ->count(DB::raw('DISTINCT m1.client_id'));
    ```

3. **Disponibilité des modérateurs** : Les modérateurs sans aucune conversation en cours sont considérés comme prioritaires pour les nouvelles attributions.

4. **Équité entre modérateurs** : Si les critères précédents ne permettent pas de départager, le système assigne le client au modérateur ayant reçu le moins d'attributions récentes.

### 6.2 Analyse approfondie du processus d'attribution

Le processus d'attribution se déroule comme suit :

1. **Détection des messages nécessitant une réponse** :

    - Le système utilise une requête SQL optimisée pour identifier les derniers messages clients sans réponse :

    ```php
    $latestClientMessages = DB::table('messages as m1')
        ->select(
            'm1.client_id',
            'm1.profile_id',
            DB::raw('MAX(m1.id) as last_message_id'),
            DB::raw('MAX(m1.created_at) as last_message_time')
        )
        ->where('m1.is_from_client', true)
        ->where('m1.created_at', '>', now()->subHours(24))
        ->groupBy('m1.client_id', 'm1.profile_id')
        ->get();
    ```

2. **Vérification des modérateurs disponibles** :

    - Le système identifie les modérateurs actifs en ligne :

    ```php
    $onlineModerators = User::where('type', 'moderateur')
        ->where('status', 'active')
        ->get();
    ```

3. **Évaluation de la charge de travail** :

    - Pour chaque modérateur, le système calcule précisément le nombre de conversations sans réponse, comme décrit précédemment.

4. **Décision de maintien ou de transfert** :

    - Si un modérateur gère déjà le profil concerné, le système vérifie si sa charge de travail est raisonnable :

    ```php
    if ($currentAssignment) {
        $currentModeratorWorkload = $workloads[$currentAssignment->user_id] ?? PHP_INT_MAX;
        $minWorkload = min($workloads);

        // Conserver le profil avec le modérateur actuel si sa charge n'est pas trop élevée
        if ($currentModeratorWorkload <= $minWorkload + 1) {
            return User::find($currentAssignment->user_id);
        }
    }
    ```

5. **Sélection finale** :

    - Le système sélectionne le modérateur avec la charge de travail minimale :

    ```php
    $minWorkload = PHP_INT_MAX;
    $selectedModeratorId = null;

    foreach ($workloads as $modId => $workload) {
        if ($workload < $minWorkload) {
            $minWorkload = $workload;
            $selectedModeratorId = $modId;
        }
    }
    ```

6. **Attribution du profil si nécessaire** :

    - Si le modérateur sélectionné n'a pas encore le profil attribué, le système lui attribue automatiquement :

    ```php
    if (!$hasProfile) {
        $profile = Profile::find($profileId);
        // Vérifier si le modérateur a déjà un profil principal
        $hasPrimaryProfile = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->exists();

        $assignment = $this->assignProfileToModerator($moderator, $profile, !$hasPrimaryProfile);
    }
    ```

7. **Notification en temps réel** :
    - Le modérateur est immédiatement notifié grâce à l'événement `ClientAssigned` :
    ```php
    event(new ClientAssigned($moderator, $client, $profile));
    ```

### 6.3 Avantages de l'équilibrage de charge

Cette approche sophistiquée présente plusieurs avantages :

-   **Répartition équitable du travail** : Aucun modérateur n'est surchargé pendant que d'autres sont inactifs.
-   **Continuité des conversations** : Le système favorise la constance dans les échanges.
-   **Temps de réponse optimisé** : Les clients reçoivent des réponses plus rapidement.
-   **Adaptation dynamique** : Le système s'adapte automatiquement aux fluctuations d'activité.
-   **Transparence pour les clients** : L'expérience client reste fluide et personnalisée.

### 6.4 Mécanisme de libération des profils

Le système comprend également un mécanisme intelligent de libération des profils pour les modérateurs inactifs :

```php
public function releaseRespondedProfiles(User $moderator): int
{
    $released = 0;
    $assignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
        ->where('is_active', true)
        ->get();

    foreach ($assignments as $assignment) {
        // Vérifier s'il reste des messages sans réponse pour ce profil
        $hasUnansweredMessages = DB::table('messages as m1')
            ->where('profile_id', $assignment->profile_id)
            ->where('is_from_client', true)
            ->where('created_at', '>', now()->subMinutes(30))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('messages as m2')
                    ->whereRaw('m2.client_id = m1.client_id')
                    ->whereRaw('m2.profile_id = m1.profile_id')
                    ->where('m2.is_from_client', false)
                    ->whereRaw('m2.created_at > m1.created_at');
            })
            ->exists();

        // Libérer le profil si tous les messages ont reçu une réponse et qu'il n'est pas le profil principal
        if (!$hasUnansweredMessages && !$assignment->is_primary) {
            $assignment->is_active = false;
            $assignment->save();
            $released++;
        }
    }

    return $released;
}
```

Cette fonction libère automatiquement les profils qui ne sont plus nécessaires, permettant ainsi de les réattribuer à d'autres modérateurs si besoin.

## 7. Interface Utilisateur du Modérateur

L'interface utilisateur des modérateurs a été optimisée pour une meilleure gestion des conversations et des notifications :

### 7.1 Structure générale

L'interface est divisée en trois sections principales :

1. **Section Clients (à gauche)**

    - Onglet "Client attribué" :
        - Liste des clients en attente de réponse
        - Notifications triées du plus récent au plus ancien
        - Indicateur de statut pour chaque client
    - Onglet "Clients disponibles" :
        - Liste des clients non attribués
        - Bouton de rafraîchissement
        - Statut de disponibilité

2. **Section Chat (au centre)**

    - En-tête avec les informations du profil actif
    - Zone de conversation avec messages horodatés
    - Zone de saisie avec indicateurs de statut
    - Chargement instantané des conversations

3. **Section Informations (à droite)**
    - Informations détaillées sur le client sélectionné
    - Historique des interactions
    - Options de gestion

### 7.2 Fonctionnalités principales

-   **Gestion multi-profils** :

    -   Affichage clair du profil actif
    -   Changement instantané entre les profils
    -   Indicateur visuel du profil principal

-   **Système de notifications** :

    -   Accumulation des notifications par ordre chronologique inverse
    -   Affichage du nom du client et du contenu du message
    -   Indicateurs visuels pour les nouveaux messages
    -   Navigation facile dans l'historique des notifications

-   **Gestion des conversations** :

    -   Chargement instantané lors du changement de client
    -   Affichage chronologique des messages
    -   Indicateurs de statut (lu/non lu)
    -   Réponse possible sans attendre les messages précédents

-   **Interface réactive** :
    -   Mise à jour en temps réel des notifications
    -   Changement fluide entre les conversations
    -   Indicateurs de chargement pour les actions longues
    -   Gestion optimisée de la mémoire

## 8. Flux de Travail Global

Le système de modération suit un flux de travail complet et intégré qui maximise l'efficacité tout en garantissant une expérience fluide pour les clients. Voici comment il fonctionne concrètement :

### 8.1. Initialisation et connexion

1. **Connexion et authentification du modérateur** :

    ```javascript
    onMounted(async () => {
        try {
            // Attendre que l'authentification soit prête
            const isReady = await waitForAuthentication();
            if (!isReady) {
                console.error(
                    "Authentification non prête, rechargement de la page..."
                );
                window.location.reload();
                return;
            }

            // Configuration et connexion aux canaux de communication
            await configureAxios();
            setupAxiosInterceptor();
            await loadAssignedData();
            // ...
        } catch (error) {
            console.error("Erreur lors de l'initialisation:", error);
        }
    });
    ```

2. **Vérification et attribution des profils** :

    ```php
    // Si aucun profil n'est attribué, essayer d'en attribuer un
    if ($assignedProfiles->isEmpty()) {
        $assignment = $this->assignmentService->assignProfileToModerator(Auth::user());
        if ($assignment) {
            $assignedProfiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());
        }
    } else {
        // Mettre à jour la dernière activité pour tous les profils
        $this->assignmentService->updateLastActivity(Auth::user());
    }
    ```

3. **Configuration des canaux de communication en temps réel** :
    ```javascript
    // Écouter les notifications d'attribution de profil
    window.Echo.private(`moderator.${moderatorId}`)
        .listen(".profile.assigned", async (data) => {
            console.log("Événement profile.assigned reçu:", data);
            await loadAssignedData();
            // ...
        })
        .listen(".client.assigned", async (data) => {
            console.log("Événement client.assigned reçu:", data);
            await loadAssignedData();
            // ...
        });
    ```

### 8.2. Gestion des clients et conversations

1. **Récupération des clients attribués** :

    ```php
    // Trouver les clients qui ont interagi avec ces profils
    // et qui attendent une réponse
    $clientsNeedingResponse = [];

    foreach ($assignedProfileIds as $profileId) {
        // Pour chaque profil, trouver les clients qui ont besoin d'une réponse
        $latestMessages = DB::table('messages as m1')
            ->select('m1.*')
            ->where('m1.profile_id', $profileId)
            ->whereIn(DB::raw('(m1.client_id, m1.id)'), function ($query) use ($profileId) {
                $query->select(DB::raw('client_id, MAX(id)'))
                    ->from('messages')
                    ->where('profile_id', $profileId)
                    ->groupBy('client_id');
            })
            ->where('m1.is_from_client', true)
            ->orderBy('m1.created_at', 'desc')
            ->get();

        // Traitement des messages pour construire la liste de clients
        foreach ($latestMessages as $message) {
            // ...
        }
    }
    ```

2. **Traitement des notifications en temps réel** :

    ```javascript
    const addNotification = (message, clientId, clientName) => {
        const notification = {
            id: Date.now(),
            message,
            clientId,
            clientName,
            timestamp: new Date(),
            read: false,
        };
        notifications.value.unshift(notification);
        // Limiter à 50 notifications maximum
        if (notifications.value.length > 50) {
            notifications.value = notifications.value.slice(0, 50);
        }
    };
    ```

3. **Chargement des messages d'une conversation** :

    ```javascript
    const loadMessages = async (clientId, page = 1, append = false) => {
        try {
            // Vérifications et préparation
            if (!currentAssignedProfile.value) {
                console.error(
                    "Impossible de charger les messages: aucun profil attribué"
                );
                return;
            }

            if (isLoadingMore.value) return;
            isLoadingMore.value = true;

            // Requête API
            const response = await axios.get("/moderateur/messages", {
                params: {
                    client_id: clientId,
                    profile_id: currentAssignedProfile.value.id,
                    page: page,
                    per_page: messagesPerPage,
                },
            });

            // Traitement de la réponse et mise à jour de l'interface
            if (response.data.messages) {
                // ...
            }
        } catch (error) {
            console.error("Erreur lors du chargement des messages:", error);
        } finally {
            isLoadingMore.value = false;
        }
    };
    ```

### 8.3. Interaction du modérateur

1. **Envoi de messages** :

    ```javascript
    async function sendMessage(retryCount = 0) {
        if (
            (!newMessage.value.trim() && !selectedFile.value) ||
            !currentAssignedProfile.value ||
            !selectedClient.value
        )
            return;

        const formData = new FormData();
        formData.append("client_id", selectedClient.value.id);
        formData.append("profile_id", currentAssignedProfile.value.id);

        if (newMessage.value.trim()) {
            formData.append("content", newMessage.value);
        }
        if (selectedFile.value) {
            formData.append("attachment", selectedFile.value);
        }

        // Création du message local pour affichage immédiat
        const localMessage = {
            id: "temp-" + Date.now(),
            content: newMessage.value,
            time: timeString,
            isFromClient: false,
            date: new Date().toISOString().split("T")[0],
        };

        // Traitement de l'envoi au serveur
        try {
            const response = await axios.post(
                "/moderateur/send-message",
                formData,
                {
                    headers: {
                        "Content-Type": "multipart/form-data",
                        "X-CSRF-TOKEN": token,
                        "X-Requested-With": "XMLHttpRequest",
                    },
                }
            );

            // Traitement de la réponse
            if (response.data.success) {
                // Mise à jour du message temporaire avec les données du serveur
            }
        } catch (error) {
            // Gestion des erreurs et tentatives de réessai
        }
    }
    ```

2. **Gestion des pièces jointes** :

    ```javascript
    function handleFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            // Vérifier le type de fichier
            if (!file.type.startsWith("image/")) {
                alert("Seules les images sont autorisées");
                return;
            }

            // Vérifier la taille du fichier (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert("La taille du fichier ne doit pas dépasser 5MB");
                return;
            }

            selectedFile.value = file;
            previewUrl.value = URL.createObjectURL(file);
        }
    }
    ```

3. **Marquage des messages comme lus** :
    ```php
    // Marquer les messages non lus comme lus
    Message::where('profile_id', $request->profile_id)
        ->where('client_id', $request->client_id)
        ->where('is_from_client', true)
        ->whereNull('read_at')
        ->update(['read_at' => now()]);
    ```

### 8.4. Mécanismes d'équilibrage et de fiabilité

1. **Détection et correction des erreurs CSRF** :

    ```javascript
    // Intercepteur pour les réponses
    axios.interceptors.response.use(
        (response) => response,
        async (error) => {
            const originalRequest = error.config;

            // Éviter les boucles infinies
            if (originalRequest._retry) {
                return Promise.reject(error);
            }

            if (
                error.response?.status === 419 ||
                (error.response?.status === 500 &&
                    error.response?.data?.message?.includes("CSRF"))
            ) {
                console.log(
                    "🔄 Erreur CSRF détectée, renouvellement du token..."
                );
                originalRequest._retry = true;

                try {
                    await axios.get("/sanctum/csrf-cookie");
                    await new Promise((resolve) => setTimeout(resolve, 100));
                    await configureAxios();

                    // Mettre à jour le token dans la requête originale
                    const newToken = getCsrfToken();
                    if (newToken) {
                        originalRequest.headers["X-CSRF-TOKEN"] = newToken;
                        return axios(originalRequest);
                    }
                } catch (retryError) {
                    console.error(
                        "Échec du renouvellement du token:",
                        retryError
                    );
                }
            }

            return Promise.reject(error);
        }
    );
    ```

2. **Logique de reprise des messages échoués** :

    ```javascript
    // AJOUT: Logique de retry améliorée
    const shouldRetry =
        (error.response?.status === 500 ||
            error.response?.status === 419 ||
            error.code === "NETWORK_ERROR" ||
            error.message.includes("timeout")) &&
        retryCount < maxRetries;

    if (shouldRetry) {
        console.log(
            `🔄 Retry ${
                retryCount + 1
            }/${maxRetries} pour l'envoi du message...`
        );

        await new Promise((resolve) =>
            setTimeout(resolve, 1000 * (retryCount + 1))
        );

        if (error.response?.status === 419 || error.response?.status === 500) {
            try {
                await axios.get("/sanctum/csrf-cookie");
                await configureAxios();
                console.log("🔄 Token CSRF renouvelé");
            } catch (tokenError) {
                console.error(
                    "Erreur lors du renouvellement du token:",
                    tokenError
                );
            }
        }

        // Recréer le FormData pour le retry
        // ...

        return sendMessage(retryCount + 1);
    }
    ```

### 8.5. Flux complet du traitement des messages

1. Le client envoie un message à un profil virtuel
2. Le système trouve le modérateur le plus disponible ayant accès à ce profil
3. Le modérateur reçoit une notification en temps réel
4. Le modérateur sélectionne la conversation pour voir tous les messages
5. Il peut répondre immédiatement via l'interface de chat
6. Le client reçoit la réponse et n'a aucune idée qu'il communique avec un modérateur
7. Le système enregistre toutes les interactions pour des analyses ultérieures

Cette architecture garantit une expérience fluide tant pour les modérateurs que pour les clients, tout en maximisant l'efficacité du traitement des messages.

## 9. Planification des Tâches avec Laravel 11

Nous avons implémenté un système automatisé pour traiter les messages clients non assignés et équilibrer la charge de travail entre les modérateurs. Ce système utilise la nouvelle architecture de tâches planifiées de Laravel 11, avec des améliorations importantes pour optimiser la réactivité et l'équilibrage de charge.

### 9.1 Structure des Tâches dans Laravel 11

Contrairement aux versions précédentes de Laravel qui utilisaient `app/Console/Kernel.php` pour définir les tâches planifiées, Laravel 11 introduit un nouveau système basé sur des classes dédiées dans le dossier `app/Tasks`.

#### `ProcessUnassignedMessagesTask`

Cette classe représente notre tâche principale pour traiter les messages non assignés, avec des délais optimisés :

```php
<?php

namespace App\Tasks;

use App\Services\ModeratorAssignmentService;
use Illuminate\Support\Facades\Log;

class ProcessUnassignedMessagesTask
{
    protected $assignmentService;

    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    // Définit quand la tâche s'exécute (toutes les 30 secondes)
    public function schedule(): string
    {
        return '*/30 * * * * *'; // Format cron avec secondes
    }

    // Code exécuté lors de chaque lancement de la tâche
    public function __invoke(): void
    {
        Log::info('Traitement des messages non assignés...');

        // Libère d'abord les profils des modérateurs inactifs (10 minutes d'inactivité)
        $releasedCount = $this->assignmentService->reassignInactiveProfiles(10);

        if ($releasedCount > 0) {
            Log::info("{$releasedCount} profil(s) libéré(s) de modérateurs inactifs.");
        }

        // Traite les messages non assignés
        $assignedCount = $this->assignmentService->processUnassignedMessages();

        Log::info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
    }
}
```

#### `ProcessUrgentMessagesTask` (NOUVEAU)

Cette nouvelle tâche se concentre spécifiquement sur les messages urgents nécessitant une attention immédiate :

```php
<?php

namespace App\Tasks;

use App\Services\ModeratorAssignmentService;
use Illuminate\Support\Facades\Log;

class ProcessUrgentMessagesTask
{
    protected $assignmentService;

    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    // Exécution toutes les 30 secondes
    public function schedule(): string
    {
        return '*/30 * * * * *'; // Format cron avec secondes
    }

    public function __invoke(): void
    {
        Log::info('Traitement des messages urgents (sans réponse depuis 2+ minutes)...');

        // Traiter seulement les messages urgents (non répondus depuis 2 minutes ou plus)
        $assignedCount = $this->assignmentService->processUnassignedMessages(true);

        if ($assignedCount > 0) {
            Log::info("{$assignedCount} client(s) urgent(s) réattribué(s) à des modérateurs.");
        }
    }
}
```

Ces tâches effectuent plusieurs actions importantes :

1. **Libération des profils inactifs** : Si un modérateur est inactif pendant plus de 10 minutes (au lieu de 30 précédemment), ses profils sont libérés.
2. **Attribution des messages standard** : Les messages clients non attribués sont assignés aux modérateurs selon leur score de disponibilité.
3. **Redistribution urgente** : Les messages sans réponse depuis plus de 2 minutes sont redistribués, même s'ils sont déjà attribués à un modérateur occupé.

### 9.2 Enregistrement des Tâches

Pour que Laravel 11 reconnaisse et exécute nos tâches, nous les avons enregistrées dans le fichier `bootstrap/tasks.php` :

```php
<?php

use App\Tasks\ProcessUnassignedMessagesTask;
use App\Tasks\ProcessUrgentMessagesTask;

return [
    // Autres tâches déjà enregistrées

    // Notre tâche pour traiter les messages non assignés (toutes les 30 secondes)
    ProcessUnassignedMessagesTask::class,

    // Notre tâche pour traiter les messages urgents (non répondus depuis 2+ minutes)
    ProcessUrgentMessagesTask::class,
];
```

### 9.3 Commande Artisan Manuelle

En plus de la tâche planifiée automatique, nous avons créé une commande Artisan qui permet d'exécuter manuellement le traitement des messages :

```php
// Dans routes/console.php
Artisan::command('messages:process', function (ModeratorAssignmentService $assignmentService) {
    $this->info('Traitement des messages non assignés...');

    $releasedCount = $assignmentService->reassignInactiveProfiles(10);
    $assignedCount = $assignmentService->processUnassignedMessages();

    $this->info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
})->purpose('Traiter manuellement les messages non assignés');
```

Cette commande peut être exécutée avec :

```bash
php artisan messages:process
```

### 9.4 Fonctionnement Global du Système de Planification

1. **Exécution périodique** : Deux tâches s'exécutent en parallèle toutes les 30 secondes :

    - `ProcessUnassignedMessagesTask` pour le traitement standard
    - `ProcessUrgentMessagesTask` pour la redistribution des messages urgents

2. **Traitement des profils inactifs** : Libère les profils des modérateurs inactifs après 10 minutes.

3. **Distribution intelligente** :

    - Calcule un score de disponibilité unique pour chaque modérateur
    - Classe les modérateurs en 3 catégories : disponible, occupé, surchargé
    - Priorité aux modérateurs disponibles, puis occupés, puis surchargés
    - Maintient la continuité des conversations quand le score du modérateur > 30

4. **Traitement des urgences** :
    - Identifie les messages sans réponse depuis 2+ minutes
    - Force la réattribution même si déjà assignés
    - Notifie immédiatement le nouveau modérateur

### 9.5 Mise en Production

Pour que le système fonctionne en production, vous devez :

1. Configurer un planificateur de tâches (Cron) pour exécuter `php artisan schedule:run` chaque minute
2. Ou, pour les environnements de développement, exécuter `php artisan schedule:work` qui lance un processus en arrière-plan

Cette approche garantit que :

-   Les clients reçoivent des réponses rapides (temps de réaction divisé par 4)
-   La charge de travail est distribuée équitablement selon un algorithme clair
-   Le système réagit immédiatement aux situations urgentes
-   La capacité de réponse reste optimale même en période de forte charge

## 10. Prochaines Étapes

Pour finaliser l'implémentation, il faudra :

1. Mettre à jour l'interface utilisateur pour prendre en charge la gestion multi-profils
2. ~~Implémenter une tâche planifiée pour exécuter régulièrement `processUnassignedMessages()`~~ ✓ (Implémenté)
3. Ajouter des statistiques sur la charge de travail des modérateurs pour l'administration
4. Mettre en place des tests automatisés pour vérifier l'équilibrage de charge
5. Développer une fonction de transfert manuel de conversations entre modérateurs

## 11. Considérations de Sécurité

Le système de modération manipule des données sensibles et gère des interactions entre différentes parties. Plusieurs mesures de sécurité ont été implémentées pour garantir la confidentialité, l'intégrité et la disponibilité du système :

### 11.1 Sécurisation des canaux de communication

Les canaux de diffusion en temps réel sont protégés par un système d'authentification robuste :

```php
// Dans routes/channels.php
Broadcast::channel('client.{id}', function ($user, $id) {
    // Un client ne peut accéder qu'à son propre canal
    return $user->isClient() && $user->id == $id;
});

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

Broadcast::channel('moderator.{id}', function ($user, $id) {
    // Un utilisateur ne peut accéder qu'à son propre canal de modérateur
    return $user->isModerator() && $user->id == $id;
});
```

### 11.2 Vérification des autorisations dans les contrôleurs

Chaque action dans le `ModeratorController` vérifie rigoureusement les autorisations avant d'accéder aux données :

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

### 11.3 Protection contre les attaques CSRF

Le système implémente des mécanismes de défense contre les attaques CSRF :

```javascript
// Configuration d'Axios avec CSRF token
const configureAxios = async () => {
    // Récupérer le token CSRF depuis les métadonnées
    let token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    // Si pas de token, essayer de le récupérer depuis window.Laravel
    if (!token && window.Laravel && window.Laravel.csrfToken) {
        token = window.Laravel.csrfToken;
    }

    // Si toujours pas de token, faire une requête pour l'obtenir
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

    if (token) {
        axios.defaults.headers.common["X-CSRF-TOKEN"] = token;
        axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
        axios.defaults.withCredentials = true;
    }
};
```

### 11.4 Validation des entrées utilisateur

Toutes les entrées utilisateur sont systématiquement validées :

```php
$request->validate([
    'client_id' => 'required|exists:users,id',
    'profile_id' => 'required|exists:profiles,id',
    'content' => 'required_without:attachment|string|max:1000',
    'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:5120',
]);
```

### 11.5 Sécurisation des fichiers téléchargés

Les pièces jointes font l'objet d'un traitement de sécurité spécifique :

```javascript
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Vérifier le type de fichier
        if (!file.type.startsWith("image/")) {
            alert("Seules les images sont autorisées");
            return;
        }

        // Vérifier la taille du fichier (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert("La taille du fichier ne doit pas dépasser 5MB");
            return;
        }

        selectedFile.value = file;
        previewUrl.value = URL.createObjectURL(file);
    }
}
```

Côté serveur, les fichiers sont également traités de manière sécurisée :

```php
// Normalise un chemin de fichier pour éviter les problèmes de double slash
private function normalizeFilePath($path)
{
    // Si le chemin commence par /storage/, le convertir en storage/
    if (strpos($path, '/storage/') === 0) {
        return 'storage' . substr($path, 8);
    }

    // Si le chemin commence par storage/ (sans slash), le laisser tel quel
    if (strpos($path, 'storage/') === 0) {
        return $path;
    }

    // Sinon, ajouter storage/ au début si nécessaire
    if (!str_starts_with($path, 'storage/') && !str_starts_with($path, '/storage/')) {
        return 'storage/' . $path;
    }

    return $path;
}
```

### 11.6 Logging et audit

Un système de journalisation détaillé est implémenté pour faciliter l'audit :

```php
Log::info("[DEBUG] Modérateur sélectionné pour attribution", [
    'moderator_id' => $moderator->id,
    'moderator_name' => $moderator->name
]);
```

Les actions critiques comme l'envoi de messages sont particulièrement surveillées :

```php
Log::info('Message envoyé avec succès', [
    'message_id' => $message->id,
    'has_attachment' => isset($message->attachment),
    'response_data' => $messageData
]);
```

### 11.7 Gestion de la confidentialité des données

-   Les conversations sont isolées par canal pour garantir que seuls les modérateurs autorisés y ont accès
-   Les données personnelles des clients sont protégées et accessibles uniquement aux modérateurs concernés
-   Les modérateurs n'ont accès qu'aux informations nécessaires à leur travail

### 11.8 Recommandations supplémentaires

Pour renforcer davantage la sécurité du système, nous recommandons :

-   Activer le chiffrement des données en transit via HTTPS
-   Mettre en place un système de détection d'intrusion
-   Effectuer des audits de sécurité réguliers
-   Former les modérateurs aux bonnes pratiques de sécurité et de confidentialité

Ces mesures de sécurité sont essentielles pour maintenir la confiance des utilisateurs et protéger les données sensibles manipulées par le système de modération.

## 12. Conclusion

Ce système amélioré permet une gestion beaucoup plus efficace des conversations entre clients et profils virtuels, avec une distribution équilibrée du travail entre les modérateurs. La possibilité pour un modérateur de gérer plusieurs profils simultanément augmente considérablement la flexibilité et l'efficacité du système, tout en garantissant que les clients reçoivent des réponses rapides à leurs messages.

L'architecture événementielle en temps réel, combinée à un équilibrage de charge intelligent, assure une expérience optimale tant pour les modérateurs que pour les clients. Les mécanismes de sécurité intégrés protègent les données sensibles et garantissent l'intégrité du système.

Le déploiement de ce système devrait considérablement améliorer la productivité des modérateurs tout en maintenant une qualité de service élevée pour les clients de l'application de rencontres.

---

Cette documentation a été mise à jour pour refléter les nouvelles fonctionnalités d'attribution équilibrée des messages, de gestion multi-profils pour les modérateurs, et les mesures de sécurité implémentées.

## 13. Optimisations de Performance et Nouvelle Gestion de la Charge

Suite à l'analyse des performances du système initial, plusieurs optimisations majeures ont été implémentées pour résoudre les problèmes identifiés.

### 13.1 Algorithme unifié d'attribution

Nous avons remplacé les quatre critères potentiellement contradictoires (continuité, charge de travail, équité, ancienneté) par un algorithme unifié basé sur un score de disponibilité :

```php
// Calculate availability score: 100 - (Conversations_actives × 20) - (Messages_en_attente × 10)
$score = 100 - ($activeConversations * 20) - ($unansweredMessages * 10);
```

Cette formule simple présente plusieurs avantages :

-   **Clarté** : Chaque facteur a un poids précis et quantifiable
-   **Équilibre** : Les conversations actives (20 points) pèsent plus que les messages individuels (10 points)
-   **Prévisibilité** : Le comportement du système devient facilement compréhensible et debuggable

### 13.2 Règles de continuité clarifiées

Les règles de maintien ou de changement de modérateur ont été clarifiées :

```php
// Step 1: If conversation exists AND moderator's score > 30, keep the same moderator
if ($currentAssignment) {
    $currentModeratorId = $currentAssignment->user_id;
    $currentModeratorScore = $moderatorScores[$currentModeratorId]['score'] ?? 0;

    // Check if there's a conversation between this client and profile
    $hasExistingConversation = DB::table('messages')
        ->where('client_id', $clientId)
        ->where('profile_id', $profileId)
        ->exists();

    if ($hasExistingConversation && $currentModeratorScore > 30) {
        Log::info("[DEBUG] Conservation du profil avec le modérateur actuel (score > 30)", [
            'moderator_id' => $currentModeratorId,
            'score' => $currentModeratorScore
        ]);
        return User::find($currentModeratorId);
    }
}
```

**Conditions précises** :

-   Garder le même modérateur si :
    -   Une conversation existe déjà ET
    -   Le score du modérateur est supérieur à 30
-   Changer de modérateur si :
    -   Nouveau client OU
    -   Modérateur surchargé (score ≤ 30) OU
    -   Modérateur inactif depuis plus de 10 minutes

### 13.3 Gestion de surcharge en 3 niveaux

Le système implémente désormais une classification claire des modérateurs selon leur charge :

```php
// Déterminer le statut de charge
$status = 'disponible';  // Score > 50

if ($score <= 50) {
    $status = 'occupé';   // Score entre 20 et 50
}

if ($score < 20) {
    $status = 'surchargé'; // Score < 20
}
```

Cette classification est utilisée pour la sélection des modérateurs :

```php
// Filtrer les modérateurs selon leur statut de charge
$availableModerators = array_filter($moderatorScores, function ($item) {
    return $item['status'] === 'disponible'; // Score > 50
});

// Si aucun modérateur n'est disponible, prendre les occupés
if (empty($availableModerators)) {
    Log::info("[DEBUG] Aucun modérateur disponible, utilisation des modérateurs occupés");
    $availableModerators = array_filter($moderatorScores, function ($item) {
        return $item['status'] === 'occupé'; // Score entre 20 et 50
    });
}

// En dernier recours, prendre même les surchargés
if (empty($availableModerators)) {
    Log::info("[DEBUG] Aucun modérateur occupé, utilisation des modérateurs surchargés");
    $availableModerators = $moderatorScores;
}
```

### 13.4 Délais optimisés pour une réactivité maximale

Tous les délais du système ont été revus pour améliorer la réactivité :

| Paramètre                 | Ancienne valeur | Nouvelle valeur | Amélioration   |
| ------------------------- | --------------- | --------------- | -------------- |
| Vérification des messages | 2 minutes       | 30 secondes     | 4× plus rapide |
| Inactivité modérateur     | 30 minutes      | 10 minutes      | 3× plus rapide |
| Redistribution urgente    | Non implémentée | 2 minutes       | Nouveau        |

Cette accélération permet :

-   Une prise en charge plus rapide des nouveaux messages
-   Une libération plus rapide des profils inutilisés
-   Une récupération immédiate des messages "abandonnés"

### 13.5 Gestion prioritaire des messages urgents

Le système identifie et traite en priorité les messages urgents (sans réponse depuis 2+ minutes) :

```php
// Pour les messages urgents, forcer la réattribution même si le modérateur actuel a le profil
$forceReassign = $urgentOnly || ($client['is_urgent'] ?? false);

$moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id'], $forceReassign);
```

Cette fonctionnalité est essentielle pour garantir qu'aucun message ne reste sans réponse trop longtemps, même en cas de surcharge temporaire du système.

### 13.6 Tâche dédiée pour les messages urgents

Une nouvelle tâche planifiée dédiée aux messages urgents a été créée :

```php
class ProcessUrgentMessagesTask
{
    // Exécution toutes les 30 secondes
    public function schedule(): string
    {
        return '*/30 * * * * *';
    }

    public function __invoke(): void
    {
        Log::info('Traitement des messages urgents (sans réponse depuis 2+ minutes)...');

        // Traiter seulement les messages urgents (non répondus depuis 2 minutes ou plus)
        $assignedCount = $this->assignmentService->processUnassignedMessages(true);

        if ($assignedCount > 0) {
            Log::info("{$assignedCount} client(s) urgent(s) réattribué(s) à des modérateurs.");
        }
    }
}
```

Cette tâche spécifique assure que les messages urgents sont traités immédiatement, sans attendre le cycle normal de traitement.

### 13.7 Bénéfices des optimisations implémentées

Ces améliorations apportent des bénéfices significatifs au système de modération :

1. **Résolution des conflits d'attribution** : L'algorithme unifié élimine les décisions contradictoires.
2. **Clarté du calcul de charge** : Le score unique remplace les métriques ambiguës.
3. **Réactivité accrue** : Les délais réduits garantissent des temps de réponse plus courts.
4. **Meilleure gestion des pics d'activité** : La classification en 3 niveaux permet une adaptation dynamique.
5. **Filet de sécurité pour les messages oubliés** : La détection et redistribution des messages urgents évite les oublis.
6. **Visibilité sur les performances** : Les logs détaillés facilitent l'optimisation continue.

Ces optimisations transforment un système fonctionnel en un système hautement performant, capable de gérer efficacement des volumes importants de messages clients tout en maintenant une expérience utilisateur optimale.
