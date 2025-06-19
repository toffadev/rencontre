# Architecture et Fonctionnement de la Partie Cliente

## Introduction

Ce document présente une analyse détaillée de l'architecture et du fonctionnement de la partie cliente de l'application de rencontre. L'application est construite avec Laravel (backend) et Vue.js avec Inertia.js (frontend), utilisant une architecture moderne avec des WebSockets pour les communications en temps réel.

## Structure Globale de l'Application

L'application est divisée en deux parties principales :

-   **Partie Cliente** : Interface utilisateur pour les clients de l'application de rencontre
-   **Partie Administrative/Modération** : Interface pour les administrateurs et modérateurs

Ce document se concentre sur la partie cliente et explique comment les différents composants interagissent entre eux.

## 1. Point d'Entrée de l'Application

### resources/views/app.blade.php

Ce fichier est le point d'entrée HTML principal de l'application. Il contient :

-   La structure HTML de base avec les balises meta et les liens vers les ressources CSS
-   L'initialisation des données utilisateur côté client via `window.Laravel`
-   La détection du type d'utilisateur (client/admin) pour charger le bon script JS
-   Le tag `@inertia` qui sert de point de montage pour l'application Vue.js

```html
<!-- Extrait important -->
<script>
    window.Laravel = {!! json_encode([
        'csrfToken' => csrf_token(),
        'user' => [
            'id' => auth()->id(),
            'type' => auth()->user()->type,
            'name' => auth()->user()->name
        ],
        'appUrl' => config('app.url')
    ]) !!};
</script>
```

Ce fichier détermine également quel bundle JavaScript charger (client.js ou admin.js) en fonction de l'URL actuelle.

## 2. Initialisation JavaScript

### resources/js/client.js

Ce fichier est le point d'entrée JavaScript pour la partie cliente. Il initialise :

-   L'application Vue.js avec Inertia.js
-   Le store Pinia pour la gestion de l'état
-   Les configurations Axios pour les requêtes HTTP
-   Les écouteurs d'événements pour la navigation Inertia

```javascript
// Initialisation de l'application
createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Client/Pages/${name}.vue`,
            import.meta.glob("./Client/Pages/**/*.vue")
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(pinia); // Utilisation de Pinia pour la gestion d'état
        // ...
        return app.mount(el);
    },
    // ...
});
```

Ce fichier contient également une logique pour initialiser le store approprié (client ou modérateur) en fonction du type d'utilisateur connecté.

### resources/js/bootstrap.js

Ce fichier configure les outils essentiels pour le frontend :

-   **Axios** : Configuration des en-têtes HTTP, gestion des erreurs CSRF
-   **Echo/Pusher** : Configuration des WebSockets pour la communication en temps réel
-   **Gestion des connexions WebSocket** : Logique de reconnexion et de gestion des canaux

```javascript
// Configuration d'Echo avec Pusher
window.Echo = new Echo({
    broadcaster: "pusher",
    key: "6ae46164b8889f3914b1",
    cluster: "eu",
    forceTLS: true,
    // ...
});
```

Ce fichier est crucial car il gère l'initialisation des WebSockets qui permettent les communications en temps réel (messages, notifications).

## 3. Services et Gestionnaires

### resources/js/services/WebSocketManager.js

Ce service centralise la gestion des WebSockets :

-   Gestion des connexions et reconnexions
-   Abonnement aux canaux privés
-   Surveillance de l'état de santé des connexions
-   Réabonnement automatique après déconnexion

```javascript
// Abonnement à un canal privé
async subscribeToPrivateChannel(channelName, events = {}) {
    try {
        // Vérifier si déjà abonné
        if (this.channels.has(channelName)) {
            console.log(`Déjà abonné au canal: ${channelName}`);
            return this.channels.get(channelName);
        }

        // Créer l'abonnement
        const channel = window.Echo.private(channelName);

        // Enregistrer les écouteurs d'événements
        for (const [event, callback] of Object.entries(events)) {
            channel.listen(event, callback);
        }

        // Stocker la référence au canal
        this.channels.set(channelName, channel);
        this.subscriptions.set(channelName, events);

        console.log(`✅ Abonné au canal privé: ${channelName}`);
        return channel;
    } catch (error) {
        console.error(`❌ Erreur lors de l'abonnement au canal ${channelName}:`, error);
        throw error;
    }
}
```

### resources/js/services/AuthenticationService.js

Ce service gère tout ce qui concerne l'authentification côté client :

-   Gestion des tokens CSRF
-   Intercepteurs Axios pour les erreurs d'authentification
-   Rafraîchissement automatique des tokens expirés
-   Redirection en cas de session expirée

```javascript
// Intercepteur pour gérer les erreurs d'authentification
axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Éviter les boucles infinies
        if (originalRequest._retry) {
            return Promise.reject(error);
        }

        // Gérer les erreurs CSRF (419)
        if (error.response?.status === 419) {
            originalRequest._retry = true;
            await this.refreshCSRFToken();
            // Réessayer la requête avec le nouveau token
            return axios(originalRequest);
        }

        // Gérer les erreurs d'authentification
        if (error.response?.status === 401) {
            this.handleAuthenticationFailure();
        }

        return Promise.reject(error);
    }
);
```

## 4. Gestion de l'État avec Pinia

### resources/js/stores/clientStore.js

Ce store Pinia centralise l'état de l'application cliente :

-   Gestion des messages et conversations
-   Gestion des points et transactions
-   État des profils bloqués ou signalés
-   Abonnement aux canaux WebSocket

```javascript
// Structure de l'état
state: () => ({
    loading: false,
    initialized: false,
    clientId: null,
    clientName: "",
    messagesMap: {}, // Messages organisés par profil
    conversationStates: {}, // État des conversations
    points: {
        balance: 0,
        transactions: [],
    },
    blockedProfileIds: [],
    reportedProfiles: [],
    channelSubscribed: false,
    errors: {},
});
```

Le store contient également des actions pour charger les données, envoyer des messages, et gérer les WebSockets :

```javascript
// Exemple d'action pour envoyer un message
async sendMessage({ profileId, content, file }) {
    // Vérifications
    if ((!content || !content.trim()) && !file) {
        console.warn('⚠️ Tentative d\'envoi de message vide');
        return;
    }

    // Logique d'envoi de message
    // ...
}

// Configuration des écouteurs WebSocket
setupClientListeners() {
    if (this.channelSubscribed) return;

    const clientId = this.clientId;
    if (!clientId) {
        console.error('❌ Impossible de configurer les écouteurs: ID client manquant');
        return;
    }

    const channelName = `client.${clientId}`;

    // S'abonner au canal client avec retry
    const subscribeWithRetry = () => {
        // Logique d'abonnement avec retry
        // ...
    };

    subscribeWithRetry();
    this.channelSubscribed = true;
}
```

## 5. Backend - Contrôleurs et Services

### app/Http/Controllers/Client/HomeController.php

Ce contrôleur gère la page d'accueil de la partie cliente :

-   Affichage des profils actifs filtrés selon les préférences
-   Enregistrement des connexions WebSocket
-   Vérification de l'état des connexions

```php
/**
 * Display the home page with active profiles
 */
public function index()
{
    $user = auth()->user();
    $clientProfile = $user->clientProfile;

    // Redirect to profile setup if not completed
    if (!$clientProfile || !$clientProfile->profile_completed) {
        return redirect()->route('profile.setup');
    }

    // Get active profiles with their photos and user (moderator)
    $profiles = Profile::with(['photos', 'mainPhoto', 'user'])
        ->where('status', 'active')
        ->where('gender', $clientProfile->seeking_gender) // Filter by gender preference
        ->latest()
        ->take(10)
        ->get();

    // Enregistrer la connexion WebSocket du client
    if (Auth::check()) {
        $this->webSocketHealthService->registerConnection(
            Auth::id(),
            Auth::user()->type,
            request()->header('X-Socket-ID') ?? uniqid('conn_')
        );
    }

    return Inertia::render('Home', [
        'profiles' => $profiles,
        'user' => $user,
    ]);
}
```

### app/Services/WebSocketHealthService.php

Ce service gère la santé et le suivi des connexions WebSocket :

-   Enregistrement des nouvelles connexions
-   Surveillance de l'activité des connexions
-   Nettoyage des connexions inactives
-   Statistiques sur les connexions actives

```php
/**
 * Enregistre une nouvelle connexion
 */
public function registerConnection(string $userId, string $userType, string $connectionId): bool
{
    try {
        $key = $this->redisPrefix . 'connection:' . $connectionId;
        $userKey = $this->redisPrefix . 'user:' . $userType . ':' . $userId;

        // Stocker les informations de connexion
        $data = [
            'user_id' => $userId,
            'user_type' => $userType,
            'connection_id' => $connectionId,
            'connected_at' => Carbon::now()->toDateTimeString(),
            'last_activity' => Carbon::now()->toDateTimeString(),
            'client_ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        Redis::hmset($key, $data);
        Redis::expire($key, 86400); // Expire après 24 heures

        // Associer cette connexion à l'utilisateur
        Redis::sadd($userKey, $connectionId);

        // ...

        return true;
    } catch (\Exception $e) {
        Log::error("Erreur lors de l'enregistrement de la connexion WebSocket: {$e->getMessage()}");
        return false;
    }
}
```

### app/Events/MessageSent.php

Cet événement est diffusé via WebSockets lorsqu'un message est envoyé :

-   Définition des canaux de diffusion (client et profil)
-   Format des données envoyées au frontend
-   Configuration pour diffusion immédiate (ShouldBroadcastNow)

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

## 6. Interface Utilisateur Vue.js

### resources/js/Client/Pages/Home.vue

Ce composant Vue est la page d'accueil principale pour les clients :

-   Affichage du carousel de profils
-   Interface de chat avec les profils
-   Gestion des messages et conversations
-   Affichage des points et options d'achat

```vue
<template>
    <MainLayout>
        <div class="flex flex-col gap-6">
            <!-- Carousel des profils -->
            <ProfileCarousel
                :profiles="profiles"
                @showActions="showProfileActions"
            />

            <!-- Section principale -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Liste des conversations -->
                <ActiveConversations
                    :profiles="filteredProfiles"
                    :selected-profile="selectedProfile"
                    :messages="messagesMap"
                    :remaining-points="remainingPoints"
                    :conversation-states="conversationStates"
                    @select="selectProfile"
                />

                <!-- Section Chat -->
                <div class="w-full lg:w-2/3">
                    <!-- Chat Content -->
                    <div
                        v-if="selectedProfile"
                        class="bg-white rounded-xl shadow-md overflow-hidden flex flex-col"
                    >
                        <!-- Chat Header -->
                        <!-- Chat Messages -->
                        <!-- Chat Input -->
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
```

## 7. Configuration des Canaux WebSocket

### routes/channels.php

Ce fichier définit les règles d'autorisation pour les canaux WebSocket :

-   Canaux privés pour les clients
-   Canaux privés pour les profils
-   Canaux privés pour les modérateurs
-   Règles de sécurité pour l'accès aux canaux

```php
// Canal privé pour les clients (accessible uniquement par le client lui-même)
Broadcast::channel('client.{id}', function ($user, $id) {
    $isClient = method_exists($user, 'isClient') ? $user->isClient() : ($user->type === 'client');
    $authorized = (int) $user->id === (int) $id && $isClient;

    Log::info("[CHANNELS] Vérification du canal client.{$id}", [
        'user_id' => $user->id,
        'user_type' => $user->type ?? 'unknown',
        'request_id' => $id,
        'is_client' => $isClient ? 'OUI' : 'NON',
        'authorized' => $authorized ? 'OUI' : 'NON'
    ]);

    return $authorized;
});
```

## 8. Routes Web

### routes/web.php

Ce fichier définit toutes les routes HTTP de l'application :

-   Routes d'authentification
-   Routes spécifiques aux clients (protégées par middleware)
-   Routes pour les messages et conversations
-   Routes pour la gestion des points
-   Routes pour les signalements de profils

```php
// Routes qui nécessitent une authentification client uniquement
Route::middleware(['auth', 'client_only'])->group(function () {
    // Page d'accueil client
    Route::get('/', [HomeController::class, 'index'])->name('client.home');

    // Routes pour les messages du client
    Route::get('/messages', [App\Http\Controllers\Client\MessageController::class, 'getMessages'])->name('client.messages');
    Route::post('/send-message', [App\Http\Controllers\Client\MessageController::class, 'sendMessage'])->name('client.send-message');
    Route::post('/messages/mark-as-read', [App\Http\Controllers\Client\MessageController::class, 'markAsRead'])->name('client.messages.mark-as-read');
    Route::get('/active-conversations', [App\Http\Controllers\Client\MessageController::class, 'getActiveConversations'])->name('client.active-conversations');

    // Routes pour la gestion des points
    Route::get('/points/data', [App\Http\Controllers\Client\PointController::class, 'getPointsData'])->name('client.points.data');
    Route::post('/points/checkout', [App\Http\Controllers\Client\PointController::class, 'createCheckoutSession'])->name('client.points.checkout');

    // ...
});
```

## 9. Initialisation de l'Application Laravel

### bootstrap/app.php

Ce fichier configure l'application Laravel :

-   Middleware globaux et nommés
-   Configuration des routes
-   Configuration des tâches planifiées
-   Middleware spécifiques pour les WebSockets

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Base Web Middleware
        $middleware->web([
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Named Middleware
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'client_only' => \App\Http\Middleware\ClientOnlyMiddleware::class,
            'broadcast_auth' => \App\Http\Middleware\EnsureBroadcastAuthentication::class,
            // ...
        ]);
    })
    // ...
    ->create();
```

## Flux d'Exécution de l'Application Cliente

1. **Chargement Initial**

    - L'utilisateur accède à l'URL de l'application
    - Le serveur charge `app.blade.php` qui initialise les données utilisateur
    - Le script `client.js` est chargé et initialise l'application Vue.js/Inertia

2. **Initialisation des Services**

    - `bootstrap.js` configure Axios et Echo/Pusher
    - `WebSocketManager.js` établit la connexion WebSocket
    - `AuthenticationService.js` configure les intercepteurs Axios

3. **Chargement de l'État**

    - Le store Pinia (`clientStore.js`) est initialisé
    - Les données utilisateur, points et conversations sont chargées
    - Les écouteurs WebSocket sont configurés

4. **Rendu de l'Interface**

    - Le composant `Home.vue` est rendu avec les données
    - Les profils actifs sont affichés dans le carousel
    - Les conversations actives sont chargées

5. **Communication en Temps Réel**

    - L'utilisateur envoie un message via l'interface
    - Le message est envoyé au serveur via Axios
    - Le serveur diffuse l'événement `MessageSent` via WebSockets
    - Tous les clients concernés reçoivent la mise à jour en temps réel

6. **Gestion des Déconnexions**
    - Le `WebSocketManager` détecte les déconnexions
    - Il tente de se reconnecter automatiquement
    - Le `WebSocketHealthService` nettoie les connexions inactives

## Conclusion

La partie cliente de l'application est construite avec une architecture moderne qui combine :

-   **Laravel** pour le backend robuste et sécurisé
-   **Vue.js/Inertia.js** pour une interface utilisateur réactive
-   **WebSockets/Pusher** pour les communications en temps réel
-   **Pinia** pour la gestion centralisée de l'état

Cette architecture permet une expérience utilisateur fluide avec des mises à jour en temps réel, tout en maintenant une séparation claire entre le frontend et le backend.
