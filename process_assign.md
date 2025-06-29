# Processus d'Assignation des Profils et Clients

Ce document explique en détail le processus d'assignation des profils virtuels aux modérateurs et des clients aux profils dans l'application, avec les extraits de code correspondants.

## 1. Structure de Données et Modèles Clés

### ModeratorProfileAssignment

Ce modèle est central dans le système d'assignation:

```php
class ModeratorProfileAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'is_active',
        'is_primary',
        'is_exclusive',
        'is_currently_active',
        'last_activity',
        'last_message_sent',
        'last_typing',
        'priority_score',
        'conversation_ids',
        'active_conversations_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'is_exclusive' => 'boolean',
        'is_currently_active' => 'boolean',
        'last_activity' => 'datetime',
        'last_message_sent' => 'datetime',
        'last_typing' => 'datetime',
        'conversation_ids' => 'array',
        'active_conversations_count' => 'integer',
        'priority_score' => 'integer',
    ];
}
```

## 2. Processus d'Assignation des Profils aux Modérateurs

### Connexion du Modérateur

1. **Frontend - Chargement de la page Moderator.vue**

```javascript
// Moderator.vue
onMounted(async () => {
    try {
        console.log("🚀 Initialisation du composant Moderator...");

        // S'assurer que la connexion WebSocket est établie
        const connected = await ensureWebSocketConnection();

        // Initialiser le store du modérateur
        await moderatorStore.initialize();

        // Envoyer un heartbeat initial pour mettre à jour le statut en ligne
        await moderatorStore.sendHeartbeat();

        // Configurer les écouteurs spécifiques au modérateur
        if (currentAssignedProfile.value) {
            moderatorStore.setupProfileListeners(
                currentAssignedProfile.value.id
            );
        }
    } catch (error) {
        console.error(
            "❌ Erreur lors de l'initialisation du composant Moderator:",
            error
        );
    }
});
```

2. **Frontend - Initialisation du Store Moderator**

```javascript
// moderatorStore.js
async initialize() {
    try {
        console.log('🚀 Initialisation du ModeratorStore...');

        // Charger les données du modérateur
        await this.loadModeratorData();

        // S'assurer que le WebSocketManager est initialisé
        this.webSocketStatus = webSocketManager.getConnectionStatus();
        if (this.webSocketStatus !== 'connected') {
            console.log('⏳ Attente de l\'initialisation du WebSocketManager...');
            await webSocketManager.initialize();
            this.webSocketStatus = webSocketManager.getConnectionStatus();
        }

        // Charger les profils attribués
        await this.loadAssignedProfiles();

        // Si un profil principal est attribué, charger les clients
        if (this.currentAssignedProfile) {
            await this.loadAssignedClients();

            // Configurer les écouteurs WebSocket pour le profil principal
            this.setupWebSocketListeners();
        }

        // Configurer les écouteurs WebSocket pour le modérateur
        this.setupModeratorWebSocketListeners();

        console.log('✅ ModeratorStore initialisé avec succès');
        this.initialized = true;
        this.startHeartbeat();
        return true;
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation du ModeratorStore:', error);
        // Gestion des erreurs...
    }
}
```

3. **Frontend - Chargement des Profils Assignés**

```javascript
// moderatorStore.js
async loadAssignedProfiles() {
    this.loading = true;
    this.errors.profiles = null;

    try {
        console.log('🔍 Chargement des profils attribués...');
        const response = await axios.get('/moderateur/profile');

        if (response.data.profiles) {
            this.assignedProfiles = response.data.profiles;

            // Définir le profil principal
            if (response.data.primaryProfile) {
                this.currentAssignedProfile = response.data.primaryProfile;
            } else if (this.assignedProfiles.length > 0) {
                // Si aucun profil principal n'est défini mais des profils sont attribués
                this.currentAssignedProfile = this.assignedProfiles.find(p => p.isPrimary) || this.assignedProfiles[0];
            } else {
                this.currentAssignedProfile = null;
            }
        } else {
            this.assignedProfiles = [];
            this.currentAssignedProfile = null;
        }
    } catch (error) {
        console.error('❌ Erreur lors du chargement des profils:', error);
        this.errors.profiles = 'Erreur lors du chargement des profils';
    } finally {
        this.loading = false;
    }
}
```

4. **Backend - Récupération des Profils Assignés**

```php
// ModeratorController.php
public function getAssignedProfile()
{
    $user = auth()->user();

    // Récupérer tous les profils assignés à ce modérateur
    $assignments = ModeratorProfileAssignment::where('user_id', $user->id)
        ->where('is_active', true)
        ->with('profile')
        ->get();

    // Récupérer le profil principal
    $primaryAssignment = $assignments->firstWhere('is_primary', true);

    // Si aucun profil principal n'est défini mais des profils sont assignés
    if (!$primaryAssignment && $assignments->isNotEmpty()) {
        $primaryAssignment = $assignments->first();
        $primaryAssignment->is_primary = true;
        $primaryAssignment->save();
    }

    // Si aucun profil n'est assigné, en attribuer un automatiquement
    if ($assignments->isEmpty()) {
        $assignment = $this->assignmentService->assignProfileToModerator($user->id, null, true);
        if ($assignment) {
            $primaryAssignment = $assignment;
        }
    }

    return response()->json([
        'profiles' => $assignments->map(function ($assignment) {
            return $assignment->profile;
        }),
        'primaryProfile' => $primaryAssignment ? $primaryAssignment->profile : null
    ]);
}
```

5. **Backend - Service d'Attribution**

```php
// ModeratorAssignmentService.php
public function assignProfileToModerator($moderatorId, $profileId = null, $isPrimary = false)
{
    // Si aucun profil n'est spécifié, trouver le profil le plus urgent
    if (!$profileId) {
        $profileId = $this->findMostUrgentProfile();
    }

    // Vérifier si le profil existe
    $profile = Profile::find($profileId);
    if (!$profile) {
        Log::warning("Profil introuvable lors de l'attribution", [
            'profile_id' => $profileId
        ]);
        return null;
    }

    // Vérifier si le modérateur existe
    $moderator = User::find($moderatorId);
    if (!$moderator || $moderator->type !== 'moderateur') {
        Log::warning("Modérateur introuvable ou invalide lors de l'attribution", [
            'moderator_id' => $moderatorId
        ]);
        return null;
    }

    // Vérifier si ce modérateur a déjà ce profil attribué
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

    // Vérifier si d'autres modérateurs ont déjà ce profil attribué
    $otherActiveAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
        ->where('is_active', true)
        ->where('user_id', '!=', $moderatorId)
        ->get();

    // Si un seul client a des messages en attente et le profil est déjà attribué à un autre modérateur
    if ($pendingClientsCount <= 1 && $otherActiveAssignments->isNotEmpty()) {
        Log::info("Profil {$profileId} déjà attribué à un autre modérateur et n'a qu'un seul client en attente");
        return null;
    }

    // Si le modérateur a déjà ce profil, mettre à jour l'attribution
    if ($existingAssignment) {
        $existingAssignment->is_active = true;
        $existingAssignment->last_activity = now();

        if ($isPrimary) {
            // Désactiver tous les autres profils principaux
            ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('is_primary', true)
                ->where('id', '!=', $existingAssignment->id)
                ->update(['is_primary' => false]);

            $existingAssignment->is_primary = true;
        }

        $existingAssignment->save();
        return $existingAssignment;
    }

    // Créer une nouvelle attribution
    $assignment = new ModeratorProfileAssignment([
        'user_id' => $moderatorId,
        'profile_id' => $profileId,
        'is_active' => true,
        'is_primary' => $isPrimary,
        'conversation_ids' => [],
        'active_conversations_count' => 0,
        'last_activity' => now()
    ]);

    $assignment->save();

    // Déclencher l'événement d'attribution
    event(new ProfileAssigned($moderator, $profileId, $assignment->id, $isPrimary));

    return $assignment;
}
```

6. **Backend - Événement d'Attribution de Profil**

```php
// ProfileAssigned.php
class ProfileAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderator;
    public $profileId;
    public $assignmentId;
    public $isShared;

    public function __construct($moderator, $profileId, $assignmentId)
    {
        $this->moderator = $moderator;
        $this->profileId = $profileId;
        $this->assignmentId = $assignmentId;

        // Vérifier si ce profil est déjà attribué à d'autres modérateurs
        $this->isShared = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->where('id', '!=', $assignmentId)
            ->exists();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }
}
```

7. **Frontend - Réception de l'Événement d'Attribution**

```javascript
// moderatorStore.js
setupModeratorWebSocketListeners() {
    if (!this.moderatorId) {
        console.warn('⚠️ Impossible de configurer les écouteurs WebSocket: ID du modérateur non disponible');
        return;
    }

    console.log(`🔄 Configuration des écouteurs WebSocket pour le modérateur ${this.moderatorId}...`);

    // S'abonner au canal du modérateur
    webSocketManager.subscribeToPrivateChannel(`moderator.${this.moderatorId}`, {
        '.profile.assigned': async (data) => {
            console.log('📩 Événement profile.assigned reçu:', data);

            // Recharger les données après l'attribution d'un profil
            await this.loadAssignedProfiles();

            // Si le profil attribué est différent du profil actuel et qu'il est principal
            if (data.profile &&
                data.profile.id !== this.currentAssignedProfile?.id &&
                data.is_primary) {

                // Démarrer le compte à rebours pour le changement de profil
                this.startProfileTransition(data.profile);

                // Attendre la fin du compte à rebours
                await new Promise(resolve => {
                    setTimeout(resolve, 3000); // 3 secondes de compte à rebours
                });

                // Activer l'état de chargement global
                this.profileTransition.loadingData = true;

                try {
                    // Réinitialiser le client sélectionné et vider le chat avant de changer de profil
                    this.selectedClient = null;

                    // Mettre à jour le profil principal
                    this.currentAssignedProfile = data.profile;

                    // Recharger les clients
                    await this.loadAssignedClients();

                    // Configurer les écouteurs WebSocket pour le nouveau profil
                    this.setupWebSocketListeners();

                    // Si un client est associé à ce changement de profil
                    if (data.client_id) {
                        // Charger les messages du client
                        await this.loadMessages(data.client_id);

                        // Trouver et sélectionner le client
                        const clientInfo = this.assignedClients.find(c => c.id === data.client_id);
                        if (clientInfo) {
                            this.selectedClient = clientInfo;
                        }
                    }
                } catch (error) {
                    console.error('❌ Erreur lors du chargement des données du nouveau profil:', error);
                } finally {
                    // Terminer la transition
                    this.endProfileTransition();
                }
            }
        }
    });
}
```

### Rotation Automatique des Profils

1. **Tâche Planifiée - RotateModeratorProfilesTask**

```php
// RotateModeratorProfilesTask.php
public function __invoke()
{
    Log::info('Démarrage de la tâche de rotation des profils');

    // 1. Identifier les profils avec des messages en attente
    $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

    // 2. Identifier les modérateurs inactifs sur leurs profils actuels
    $inactiveAssignments = $this->getInactiveAssignments();

    // 3. Pour chaque profil avec des messages en attente, essayer de l'attribuer
    foreach ($profilesWithPendingMessages as $profileId => $pendingCount) {
        // Vérifier si ce profil est déjà attribué à un modérateur actif
        $activeAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->where('last_activity', '>', Carbon::now()->subMinutes(15))
            ->count();

        // Si le profil n'est pas attribué à un modérateur actif, l'attribuer à un modérateur inactif
        if ($activeAssignments == 0 && count($inactiveAssignments) > 0) {
            $inactiveAssignment = $inactiveAssignments->shift(); // Prendre le premier modérateur inactif

            // Désactiver le profil actuel
            $inactiveAssignment->is_active = false;
            $inactiveAssignment->is_primary = false;
            $inactiveAssignment->save();

            // Attribuer le nouveau profil
            $this->assignmentService->assignProfileToModerator(
                $inactiveAssignment->user_id,
                $profileId,
                true
            );

            Log::info("Profil {$profileId} attribué au modérateur {$inactiveAssignment->user_id} (rotation automatique)");
        }
    }

    Log::info('Fin de la tâche de rotation des profils');
}

protected function getInactiveAssignments()
{
    // Conserver le délai d'inactivité à 1 minute comme vous le souhaitez
    Log::info("Recherche des attributions inactives (inactivité > 1 minute)");

    $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
        ->where(function ($query) {
            $query->where('last_activity', '<', Carbon::now()->subMinutes(1))
                ->orWhereNull('last_activity');
        })
        ->with('user')
        ->get();

    return $inactiveAssignments;
}
```

2. **Service - ModeratorAssignmentService**

```php
// ModeratorAssignmentService.php
public function reassignInactiveProfiles(int $inactiveMinutes = 10): int
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

public function checkInactiveAssignments()
{
    // Trouver les attributions inactives (pas d'activité depuis 20 minutes)
    $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
        ->where('last_activity', '<', Carbon::now()->subMinutes(20))
        ->get();

    foreach ($inactiveAssignments as $assignment) {
        // Vérifier s'il y a des messages en attente pour ce profil
        $pendingMessages = Message::where('profile_id', $assignment->profile_id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->exists();

        // Si ce profil a des messages en attente, essayer de l'attribuer à un autre modérateur
        if ($pendingMessages) {
            // Trouver un modérateur actif
            $activeModerator = User::where('type', 'moderateur')
                ->where('is_online', true)
                ->where('id', '!=', $assignment->user_id)
                ->whereDoesntHave('profileAssignments', function ($query) {
                    $query->where('is_active', true)
                        ->where('active_conversations_count', '>', 5);
                })
                ->first();

            if ($activeModerator) {
                // Désactiver l'attribution actuelle
                $assignment->is_active = false;
                $assignment->save();

                // Créer une nouvelle attribution
                $this->assignProfileToModerator($activeModerator->id, $assignment->profile_id, true);
            }
        }
    }
}
```

## 3. Processus d'Assignation des Clients aux Modérateurs

### Chargement des Clients Assignés

1. **Frontend - Affichage des Clients Assignés**

```vue
<!-- Moderator.vue -->
<template>
    <!-- Tab Content: Client attribué -->
    <div v-if="activeTab === 'assigned'" class="p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Client attribué</h2>
            <div
                v-if="assignedClient.length > 0"
                class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm"
            >
                En attente de réponse
            </div>
            <div
                v-else
                class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-sm"
            >
                En attente d'attribution
            </div>
        </div>

        <div class="space-y-4">
            <!-- Liste des clients attribués -->
            <div v-if="assignedClient.length > 0" class="space-y-4">
                <div
                    v-for="client in sortedAssignedClients"
                    :key="client.id"
                    class="client-card transition duration-300"
                    @click="selectClient(client)"
                >
                    <!-- Contenu de la carte client -->
                </div>
            </div>

            <!-- État vide -->
            <div v-else class="text-center py-8">
                <p class="text-gray-500">
                    Aucun client ne vous a été attribué pour le moment.
                </p>
            </div>
        </div>
    </div>
</template>
```

2. **Frontend - Chargement des Clients dans le Store**

```javascript
// moderatorStore.js
async loadAssignedClients() {
    if (!this.currentAssignedProfile) {
        console.warn('⚠️ Impossible de charger les clients: aucun profil principal attribué');
        return;
    }

    this.loading = true;
    this.errors.clients = null;

    try {
        console.log('🔍 Chargement des clients attribués...');
        const response = await axios.get('/moderateur/clients');

        if (response.data.clients) {
            this.assignedClients = response.data.clients;
            console.log(`✅ ${this.assignedClients.length} clients chargés`);

            // Si un client est sélectionné, mettre à jour ses informations
            if (this.selectedClient) {
                const updatedClient = this.assignedClients.find(c => c.id === this.selectedClient.id);
                if (updatedClient) {
                    this.selectedClient = updatedClient;
                }
            }
        } else {
            this.assignedClients = [];
            console.warn('⚠️ Aucun client retourné par l\'API');
        }
    } catch (error) {
        console.error('❌ Erreur lors du chargement des clients:', error);
        this.errors.clients = 'Erreur lors du chargement des clients';
        this.assignedClients = [];
    } finally {
        this.loading = false;
    }
}
```

3. **Backend - Récupération des Clients Assignés**

```php
// ModeratorController.php
public function getClients()
{
    $user = auth()->user();

    // Récupérer uniquement le profil principal actif
    $assignment = ModeratorProfileAssignment::where('user_id', $user->id)
        ->where('is_active', true)
        ->where('is_primary', true)
        ->with('profile')
        ->first();

    if (!$assignment) {
        return response()->json(['clients' => []]);
    }

    $profile = $assignment->profile;
    $clientIds = $assignment->conversation_ids ?? [];

    if (empty($clientIds)) {
        return response()->json(['clients' => []]);
    }

    // Récupérer les informations sur chaque client
    $clients = [];
    foreach ($clientIds as $clientId) {
        $client = User::find($clientId);
        if (!$client) continue;

        // Récupérer le dernier message
        $lastMessage = Message::where('client_id', $clientId)
            ->where('profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->first();

        // Compter les messages non lus
        $unreadCount = Message::where('client_id', $clientId)
            ->where('profile_id', $profile->id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->count();

        $clients[] = [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => $client->avatar_url,
            'lastMessage' => $lastMessage ? $lastMessage->content : null,
            'lastMessageAt' => $lastMessage ? $lastMessage->created_at : null,
            'unreadCount' => $unreadCount,
            'profileId' => $profile->id,
            'profileName' => $profile->name,
            'profilePhoto' => $profile->main_photo_path
        ];
    }

    return response()->json(['clients' => $clients]);
}
```

4. **Backend - Processus d'Attribution des Clients**

```php
// ModeratorAssignmentService.php
public function processUnassignedMessages($urgentOnly = false)
{
    $clientsAssigned = 0;
    $clientsNeedingResponse = $this->getClientsNeedingResponse($urgentOnly);

    Log::info("[DEBUG] Traitement des messages non assignés", [
        'clients_count' => $clientsNeedingResponse->count(),
        'urgent_only' => $urgentOnly
    ]);

    // First, release any profiles that have been responded to
    $onlineModerators = User::where('type', 'moderateur')
        ->where('status', 'active')
        ->get();

    foreach ($onlineModerators as $moderator) {
        $this->releaseRespondedProfiles($moderator);
    }

    foreach ($clientsNeedingResponse as $client) {
        // Assigner le client à un modérateur
        $moderator = $this->assignClientToModerator($client['client_id'], $client['profile_id']);

        if ($moderator) {
            $clientsAssigned++;

            // Log des messages urgents traités
            if ($client['is_urgent'] ?? false) {
                Log::info("[DEBUG] Message urgent attribué", [
                    'client_id' => $client['client_id'],
                    'profile_id' => $client['profile_id'],
                    'message_age' => Carbon::parse($client['created_at'])->diffForHumans(),
                    'assigned_to' => $moderator->id
                ]);
            }
        }
    }

    return $clientsAssigned;
}

public function getClientsNeedingResponse($urgentOnly = false)
{
    // Trouve les derniers messages pour chaque paire client-profil
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

    // Pour chaque dernier message client, vérifier s'il a une réponse
    $clientsNeedingResponse = collect();

    foreach ($latestClientMessages as $clientMessage) {
        // Chercher si une réponse existe après ce message
        $hasResponse = DB::table('messages')
            ->where('client_id', $clientMessage->client_id)
            ->where('profile_id', $clientMessage->profile_id)
            ->where('is_from_client', false)
            ->where('created_at', '>', $clientMessage->last_message_time)
            ->exists();

        // Si aucune réponse n'existe, vérifier l'urgence si nécessaire
        if (!$hasResponse) {
            $messageTime = Carbon::parse($clientMessage->last_message_time);
            $isUrgent = $messageTime->diffInMinutes(now()) >= 2;

            // Ajouter à la liste si pas urgent_only OU si c'est urgent et urgent_only est true
            if (!$urgentOnly || $isUrgent) {
                $clientsNeedingResponse->push([
                    'client_id' => $clientMessage->client_id,
                    'profile_id' => $clientMessage->profile_id,
                    'message_id' => $clientMessage->last_message_id,
                    'created_at' => $clientMessage->last_message_time,
                    'is_urgent' => $isUrgent
                ]);
            }
        }
    }

    // Trier: d'abord les urgents, puis par ancienneté
    return $clientsNeedingResponse
        ->sortByDesc('is_urgent')
        ->sortBy('created_at')
        ->values();
}
```

5. **Backend - Attribution d'un Client à un Modérateur**

```php
// ModeratorAssignmentService.php
public function assignClientToModerator($clientId, $profileId)
{
    // Vérifier si ce client est déjà attribué à un modérateur pour ce profil spécifique
    $existingAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
        ->where('is_active', true)
        ->get();

    foreach ($existingAssignments as $assignment) {
        $conversations = $assignment->conversation_ids ?? [];
        if (in_array($clientId, $conversations)) {
            // Ce client est déjà attribué à un modérateur pour ce profil
            Log::info("Client déjà attribué à un modérateur pour ce profil", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => $assignment->user_id
            ]);

            // Retourner le modérateur qui a déjà ce client
            return User::find($assignment->user_id);
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

        // Attribuer le profil au modérateur disponible
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

    // Ajouter cette conversation à l'attribution
    $assignment->addConversation($clientId);

    // Déclencher l'événement d'attribution de client
    event(new ClientAssigned($moderator, $clientId, $profileId));

    return $moderator;
}
```

## 4. Gestion de l'Activité des Modérateurs

### Suivi de l'Activité

Le système suit l'activité des modérateurs pour plusieurs raisons importantes :

-   Déterminer quels modérateurs sont actifs et disponibles pour recevoir de nouvelles attributions
-   Identifier les modérateurs inactifs pour réattribuer leurs profils
-   Permettre aux modérateurs partageant un même profil de voir l'activité des autres

1. **Service ModeratorActivityService**

```php
// ModeratorActivityService.php
public function recordTypingActivity($userId, $profileId, $clientId)
{
    $assignment = ModeratorProfileAssignment::where('user_id', $userId)
        ->where('profile_id', $profileId)
        ->where('is_active', true)
        ->first();

    if ($assignment) {
        // Vérifier si la dernière activité de frappe est récente (moins de 3 secondes)
        $shouldEmitEvent = true;
        if ($assignment->last_typing) {
            $timeSinceLastTyping = $assignment->last_typing->diffInSeconds(now());
            // Ne pas émettre d'événement si moins de 3 secondes se sont écoulées depuis le dernier
            if ($timeSinceLastTyping < 3) {
                $shouldEmitEvent = false;
            }
        }

        // Mettre à jour le timestamp de dernière frappe
        $assignment->last_typing = now();
        $assignment->save();

        // N'émettre l'événement que si nécessaire
        if ($shouldEmitEvent) {
            event(new ModeratorActivityEvent($userId, $profileId, $clientId, 'typing', now()));
        }
    }
}
```

2. **Événement ModeratorActivityEvent**

```php
// ModeratorActivityEvent.php
class ModeratorActivityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderatorId;
    public $profileId;
    public $clientId;
    public $activityType;
    public $timestamp;

    public function __construct($moderatorId, $profileId, $clientId, $activityType)
    {
        $this->moderatorId = $moderatorId;
        $this->profileId = $profileId;
        $this->clientId = $clientId;
        $this->activityType = $activityType; // 'typing', 'reading', 'idle', etc.
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        // Diffuser sur le canal du profil pour que tous les modérateurs
        // qui partagent ce profil puissent voir l'activité
        return [
            new PrivateChannel('profile.' . $this->profileId),
        ];
    }
}
```

3. **Heartbeat pour Maintenir le Statut En Ligne**

```php
// ModeratorController.php
public function heartbeat()
{
    $user = auth()->user();

    if (!$user || $user->type !== 'moderateur') {
        return response()->json(['success' => false, 'message' => 'Utilisateur non autorisé'], 403);
    }

    // Mettre à jour le statut en ligne et l'horodatage de dernière activité
    $user->is_online = true;
    $user->last_online_at = now();
    $user->last_activity_at = now();
    $user->save();

    // Mettre à jour l'activité pour tous les profils attribués
    $assignments = ModeratorProfileAssignment::where('user_id', $user->id)
        ->where('is_active', true)
        ->get();

    foreach ($assignments as $assignment) {
        $assignment->last_activity = now();
        $assignment->save();
    }

    return response()->json(['success' => true]);
}
```

4. **Frontend - Heartbeat Périodique**

```javascript
// Moderator.vue
onMounted(async () => {
    // ...

    // Configurer l'intervalle de heartbeat pour maintenir le statut en ligne
    heartbeatInterval = setInterval(() => {
        moderatorStore.sendHeartbeat();
    }, 2 * 60 * 1000); // 2 minutes
});
```

### Demande de Délai avant Changement de Profil

Les modérateurs peuvent demander un délai supplémentaire avant qu'un profil leur soit retiré :

```php
// ModeratorActivityService.php
public function requestDelay($userId, $profileId, $minutes = 5)
{
    $assignment = ModeratorProfileAssignment::where('user_id', $userId)
        ->where('profile_id', $profileId)
        ->where('is_active', true)
        ->first();

    if ($assignment) {
        // Augmenter temporairement le score de priorité
        $assignment->priority_score += 10;
        $assignment->last_activity = now();
        $assignment->save();

        return true;
    }

    return false;
}
```

```javascript
// moderatorStore.js
async requestProfileChangeDelay(profileId, minutes = 5) {
    if (!this.canRequestDelay) return false;

    try {
        const response = await axios.post('/moderateur/request-delay', {
            profile_id: profileId,
            minutes: minutes,
        });

        if (response.data.status === 'success') {
            this.delayRequested = true;
            this.canRequestDelay = false;

            // Réinitialiser après un certain temps
            setTimeout(() => {
                this.canRequestDelay = true;
            }, 15 * 60 * 1000); // 15 minutes

            return true;
        }
        return false;
    } catch (error) {
        console.error('Erreur lors de la demande de délai:', error);
        return false;
    }
}
```

```vue
<!-- Moderator.vue -->
<!-- Bouton pour demander un délai supplémentaire -->
<div class="mt-2" v-if="!moderatorStore.delayRequested">
    <button @click="requestDelay"
        class="bg-white text-pink-600 border border-pink-500 px-3 py-1 rounded-md text-sm hover:bg-pink-50 transition-colors">
        <i class="fas fa-clock mr-1"></i>
        Demander 5 min supplémentaires
    </button>
</div>
```

## 5. Système de Rotation des Profils

Le système comprend une tâche planifiée qui gère la rotation automatique des profils entre les modérateurs :

1. **Tâche RotateModeratorProfilesTask**

```php
// RotateModeratorProfilesTask.php
public function __invoke()
{
    Log::info('Démarrage de la tâche de rotation des profils');

    // 1. Identifier les profils avec des messages en attente
    $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

    // 2. Identifier les modérateurs inactifs sur leurs profils actuels
    $inactiveAssignments = $this->getInactiveAssignments();

    // 3. Pour chaque profil avec des messages en attente, essayer de l'attribuer
    foreach ($profilesWithPendingMessages as $profileId => $pendingCount) {
        // Vérifier si ce profil est déjà attribué à un modérateur actif
        $activeAssignments = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->where('last_activity', '>', Carbon::now()->subMinutes(15))
            ->count();

        // Si le profil n'est pas attribué à un modérateur actif, l'attribuer à un modérateur inactif
        if ($activeAssignments == 0 && count($inactiveAssignments) > 0) {
            $inactiveAssignment = $inactiveAssignments->shift(); // Prendre le premier modérateur inactif

            // Désactiver le profil actuel
            $inactiveAssignment->is_active = false;
            $inactiveAssignment->is_primary = false;
            $inactiveAssignment->save();

            // Attribuer le nouveau profil
            $this->assignmentService->assignProfileToModerator(
                $inactiveAssignment->user_id,
                $profileId,
                true
            );

            Log::info("Profil {$profileId} attribué au modérateur {$inactiveAssignment->user_id} (rotation automatique)");
        }
    }

    Log::info('Fin de la tâche de rotation des profils');
}
```

2. **Détection des Profils avec Messages en Attente**

```php
// RotateModeratorProfilesTask.php
protected function getProfilesWithPendingMessages()
{
    $pendingProfiles = [];

    // Trouver tous les messages non lus des clients
    $pendingMessages = Message::where('is_from_client', true)
        ->whereNull('read_at')
        ->select('profile_id', \DB::raw('COUNT(*) as count'), \DB::raw('MAX(created_at) as oldest_message'))
        ->groupBy('profile_id')
        ->orderBy('oldest_message', 'asc') // Plus ancien message d'abord
        ->get();

    foreach ($pendingMessages as $message) {
        $pendingProfiles[$message->profile_id] = $message->count;
    }

    return $pendingProfiles;
}
```

3. **Détection des Modérateurs Inactifs**

```php
// RotateModeratorProfilesTask.php
protected function getInactiveAssignments()
{
    // Conserver le délai d'inactivité à 1 minute comme vous le souhaitez
    Log::info("Recherche des attributions inactives (inactivité > 1 minute)");

    $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
        ->where(function ($query) {
            $query->where('last_activity', '<', Carbon::now()->subMinutes(1))
                ->orWhereNull('last_activity');
        })
        ->with('user')
        ->get();

    return $inactiveAssignments;
}
```

## 6. Gestion des Profils Partagés

Le système permet à plusieurs modérateurs de partager un même profil, avec des mécanismes pour coordonner leur travail :

1. **Vérification des Profils Partagés**

```php
// ModeratorController.php
public function isProfileShared($profileId)
{
    // Vérifier si ce profil est attribué à plusieurs modérateurs actifs
    $assignmentCount = ModeratorProfileAssignment::where('profile_id', $profileId)
        ->where('is_active', true)
        ->count();

    return response()->json([
        'isShared' => $assignmentCount > 1
    ]);
}
```

2. **Notification des Activités entre Modérateurs**

```javascript
// moderatorStore.js
listenToSharedProfileEvents(profileId) {
    if (!window.Echo) return;

    window.Echo.private(`profile.${profileId}`)
        .listen('ModeratorActivityEvent', (event) => {
            // Mettre à jour l'état des activités des autres modérateurs
            if (event.moderatorId !== this.moderatorId) {
                this.activeModeratorsByProfile[profileId] = this.activeModeratorsByProfile[profileId] || [];

                // Ajouter ou mettre à jour l'activité du modérateur
                const existingIndex = this.activeModeratorsByProfile[profileId].findIndex(
                    m => m.moderatorId === event.moderatorId
                );

                const activityData = {
                    moderatorId: event.moderatorId,
                    clientId: event.clientId,
                    activityType: event.activityType,
                    timestamp: event.timestamp,
                };

                if (existingIndex >= 0) {
                    this.activeModeratorsByProfile[profileId][existingIndex] = activityData;
                } else {
                    this.activeModeratorsByProfile[profileId].push(activityData);
                }

                // Nettoyer les activités anciennes
                this.cleanupOldActivities();
            }
        });
}
```

3. **Affichage des Indicateurs d'Activité dans l'Interface**

```vue
<!-- Moderator.vue -->
<!-- Notification de profil partagé -->
<div v-if="isProfileShared"
    class="shared-profile-indicator bg-blue-100 text-blue-700 px-3 py-2 rounded-md mt-2">
    <div class="flex items-center">
        <i class="fas fa-users mr-2"></i>
        <span>Ce profil est partagé avec d'autres modérateurs</span>
    </div>
</div>

<!-- Indicateurs d'activité des autres modérateurs -->
<div v-if="otherModeratorsActive.length > 0"
    class="bg-gray-50 p-2 rounded-md mt-2 border border-gray-100">
    <p class="text-xs text-gray-600 font-medium">
        <i class="fas fa-user-clock mr-1"></i>
        Autres modérateurs actifs sur ce profil:
    </p>
    <div v-for="activity in otherModeratorsActive" :key="activity.moderatorId"
        class="text-xs text-gray-500 mt-1">
        <span>Modérateur #{{ activity.moderatorId }}</span>
        <span class="mx-1">•</span>
        <span>{{ activity.activityType === 'typing' ? 'écrit' : activity.activityType }}</span>
        <span class="ml-2 text-gray-400">{{ formatTime(activity.timestamp) }}</span>
    </div>
</div>
```

## 7. Flux Complet du Processus d'Attribution

1. **Connexion du Modérateur**

    - Le modérateur se connecte à l'application
    - Le système vérifie s'il a déjà des profils attribués
    - Si non, un profil lui est attribué automatiquement

2. **Attribution Automatique des Clients**

    - Une tâche planifiée vérifie régulièrement les messages non répondus
    - Les clients avec des messages en attente sont attribués aux modérateurs disponibles
    - Priorité donnée aux messages les plus anciens et aux modérateurs les moins occupés

3. **Rotation des Profils**

    - Les profils sont réattribués en fonction de l'activité des modérateurs
    - Un modérateur inactif peut voir son profil réattribué à un autre modérateur
    - Les profils avec des messages en attente sont prioritaires

4. **Gestion de l'Activité**

    - Les modérateurs envoient régulièrement des heartbeats pour indiquer qu'ils sont actifs
    - L'activité de frappe est enregistrée et partagée entre modérateurs
    - Les modérateurs peuvent demander un délai supplémentaire avant un changement de profil

5. **Profils Partagés**
    - Plusieurs modérateurs peuvent partager un même profil
    - Les activités des modérateurs sont diffusées en temps réel
    - L'interface affiche les indicateurs d'activité des autres modérateurs

Ce système complet assure une gestion efficace des conversations, évite les temps d'attente prolongés pour les clients et optimise la charge de travail des modérateurs.
