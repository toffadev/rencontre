# Documentation du Système de Messagerie Client en Temps Réel

## 1. Introduction et Vue d'ensemble

Ce document explique en détail comment nous avons implémenté la fonctionnalité de messagerie en temps réel dans l'interface client (Home.vue) de notre application de rencontres. Le concept principal est le suivant :

-   Les **clients** peuvent sélectionner un **profil** dans la liste et engager une conversation.
-   Les messages sont envoyés et reçus en temps réel grâce à **Laravel Echo** et **Laravel Reverb**.
-   Les conversations sont persistantes (stockées en base de données) et peuvent être reprises à tout moment.
-   L'interface s'adapte dynamiquement pour afficher les conversations avec le profil sélectionné.

Cette implémentation permet aux clients d'interagir avec les profils virtuels (gérés par des modérateurs) de manière fluide et en temps réel, ce qui améliore considérablement l'expérience utilisateur.

## 2. Structure du Système de Messagerie

### 2.1 Composants Principaux

Le système de messagerie client comprend plusieurs composants interdépendants :

1. **Controller** : `MessageController` pour gérer les requêtes API de messages
2. **Routes** : Définition des endpoints API pour les messages
3. **Vue Component** : `Home.vue` pour l'interface utilisateur
4. **WebSockets** : Configuration avec Laravel Echo et Reverb
5. **Channels** : Définition des canaux de diffusion pour la communication temps réel

### 2.2 Flux de Données

Voici comment les données circulent dans le système :

1. Le client sélectionne un profil dans l'interface.
2. L'application charge les messages existants pour cette conversation via une requête API.
3. Le client peut envoyer un message, qui est envoyé au serveur via API.
4. Le serveur diffuse ce message via WebSockets à tous les participants concernés.
5. Les clients et modérateurs connectés reçoivent le message en temps réel.

## 3. Contrôleur de Messages pour le Client

Nous avons créé un contrôleur dédié (`MessageController`) pour gérer les opérations liées aux messages côté client. Ce contrôleur contient deux méthodes principales :

### 3.1 `getMessages`

Cette méthode récupère l'historique des messages entre un client et un profil :

```php
public function getMessages(Request $request)
{
    $request->validate([
        'profile_id' => 'required|integer|exists:profiles,id',
    ]);

    $clientId = Auth::id();
    $profileId = $request->profile_id;

    // Récupérer les messages entre ce client et ce profil
    $messages = Message::where('profile_id', $profileId)
        ->where('client_id', $clientId)
        ->orderBy('created_at')
        ->get()
        ->map(function ($message) {
            return [
                'id' => $message->id,
                'content' => $message->content,
                'isOutgoing' => $message->is_from_client, // Pour le client, "outgoing" = is_from_client
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
            ];
        });

    // Marquer les messages non lus comme lus (uniquement les messages des profils)
    Message::where('profile_id', $profileId)
        ->where('client_id', $clientId)
        ->where('is_from_client', false)
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

    return response()->json([
        'messages' => $messages
    ]);
}
```

**Points importants à noter :**

-   La méthode valide d'abord que le `profile_id` est fourni et valide.
-   Elle récupère tous les messages entre le client authentifié et le profil spécifié.
-   Les messages sont formatés pour l'affichage, avec `isOutgoing` qui indique si le message vient du client.
-   Les messages non lus provenant du profil sont marqués comme lus.

### 3.2 `sendMessage`

Cette méthode permet au client d'envoyer un message à un profil :

```php
public function sendMessage(Request $request)
{
    $request->validate([
        'profile_id' => 'required|integer|exists:profiles,id',
        'content' => 'required|string|max:1000',
    ]);

    $clientId = Auth::id();
    $profileId = $request->profile_id;

    // Créer le nouveau message
    $message = Message::create([
        'client_id' => $clientId,
        'profile_id' => $profileId,
        'moderator_id' => null, // Pas de modérateur car vient du client
        'content' => $request->content,
        'is_from_client' => true,
    ]);

    // Diffuser l'événement de message
    event(new MessageSent($message));

    return response()->json([
        'success' => true,
        'message' => 'Message envoyé avec succès',
        'messageData' => [
            'id' => $message->id,
            'content' => $message->content,
            'isOutgoing' => true,
            'time' => $message->created_at->format('H:i'),
            'date' => $message->created_at->format('Y-m-d'),
        ]
    ]);
}
```

**Points importants à noter :**

-   La méthode valide les données entrantes (`profile_id` et `content`).
-   Elle crée un nouveau message en base de données avec `is_from_client` = true.
-   Elle déclenche l'événement `MessageSent` qui sera diffusé via WebSockets.
-   Elle renvoie les données du message pour que le frontend puisse les afficher immédiatement.

## 4. Routes pour les Messages Client

Nous avons ajouté deux routes dans `routes/web.php` pour les opérations de messagerie client :

```php
// Routes qui nécessitent une authentification client ou admin
Route::middleware(['client_or_admin'])->group(function () {
    // Autres routes...

    // Routes pour les messages du client
    Route::get('/messages', [App\Http\Controllers\Client\MessageController::class, 'getMessages'])->name('client.messages');
    Route::post('/send-message', [App\Http\Controllers\Client\MessageController::class, 'sendMessage'])->name('client.send-message');
});
```

**Points importants à noter :**

-   Ces routes sont protégées par le middleware `client_or_admin`, ce qui signifie que seuls les clients ou les administrateurs authentifiés peuvent y accéder.
-   La route GET `/messages` récupère les messages existants.
-   La route POST `/send-message` permet d'envoyer un nouveau message.

## 5. Configuration de Laravel Echo et WebSockets

### 5.1 `bootstrap.js`

Nous avons configuré Laravel Echo dans le fichier `bootstrap.js` pour permettre les communications en temps réel :

```javascript
import Echo from "laravel-echo";
import axios from "axios";

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */
window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

/**
 * Echo expose an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to easily build robust real-time web applications.
 */

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || "https") === "https",
    enabledTransports: ["ws", "wss"],
});

/**
 * Store client ID for use with Echo private channels
 */
window.clientId = document
    .querySelector('meta[name="client-id"]')
    ?.getAttribute("content");
```

**Points importants à noter :**

-   Nous utilisons Laravel Reverb comme broadcaster (serveur WebSocket).
-   La configuration utilise des variables d'environnement pour les paramètres.
-   Nous récupérons l'ID du client depuis une balise meta dans le HTML pour l'utiliser avec les canaux privés.

### 5.2 `app.blade.php`

Nous avons modifié la vue principale pour inclure l'ID du client authentifié :

```html
<!DOCTYPE html>
<html lang="fr">
    <head>
        <!-- autres balises meta -->
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        @auth
        <meta name="client-id" content="{{ auth()->id() }}" />
        @endauth
        <!-- reste du head -->
    </head>
    <body>
        <!-- ... -->
    </body>
</html>
```

**Points importants à noter :**

-   La balise meta `client-id` n'est ajoutée que si l'utilisateur est authentifié (`@auth`).
-   Cette valeur est récupérée par JavaScript pour configurer les canaux privés.

### 5.3 `HandleInertiaRequests.php`

Nous avons également modifié le middleware Inertia pour partager l'ID du client avec les composants Vue :

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'type' => $request->user()->type,
            ] : null,
        ],
        'clientId' => $request->user() ? $request->user()->id : null,
        'flash' => [
            'message' => fn() => $request->session()->get('message'),
            'error' => fn() => $request->session()->get('error'),
        ],
    ]);
}
```

**Points importants à noter :**

-   L'ajout de `'clientId'` permet d'accéder à cette valeur dans les composants Vue via `$page.props.clientId`.
-   Cette approche offre une alternative à la balise meta pour les applications Inertia.

## 6. Canaux de Diffusion (Broadcasting Channels)

Dans `routes/channels.php`, nous avons défini les canaux de diffusion pour sécuriser la communication WebSocket :

```php
// Canal privé pour les clients (accessible uniquement par le client lui-même)
Broadcast::channel('client.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && $user->type === 'client';
});

// Canal privé pour les profils (accessible par les modérateurs assignés au profil)
Broadcast::channel('profile.{profileId}', function ($user, $profileId) {
    // Si l'utilisateur est client, vérifier s'il a des conversations avec ce profil
    if ($user->type === 'client') {
        return \App\Models\Message::where('client_id', $user->id)
                               ->where('profile_id', $profileId)
                               ->exists();
    }

    // Si l'utilisateur est modérateur, vérifier s'il est assigné à ce profil
    if ($user->type === 'moderator') {
        return \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
                                                 ->where('profile_id', $profileId)
                                                 ->where('is_active', true)
                                                 ->exists();
    }

    return false;
});

// Canal privé pour les modérateurs (accessible uniquement par le modérateur lui-même)
Broadcast::channel('moderator.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && $user->type === 'moderator';
});
```

**Points importants à noter :**

-   Le canal `client.{id}` est accessible uniquement par le client correspondant.
-   Le canal `profile.{profileId}` est accessible par :
    -   Les clients qui ont déjà une conversation avec ce profil.
    -   Les modérateurs qui sont assignés à ce profil.
-   Cette configuration garantit que les messages ne sont diffusés qu'aux parties concernées.

## 7. Interface Client (Home.vue)

### 7.1 Structure du composant

Nous avons modifié le composant `Home.vue` pour gérer la sélection des profils et les conversations :

```vue
<template>
    <MainLayout>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Liste des profils (à gauche) -->
            <div
                class="w-full lg:w-1/3 bg-white rounded-xl shadow-md overflow-hidden p-4"
            >
                <!-- ... -->
                <div
                    v-for="profile in profiles"
                    @click="selectProfile(profile)"
                    :class="{
                        'border-l-4 border-pink-500':
                            selectedProfile &&
                            selectedProfile.id === profile.id,
                    }"
                >
                    <!-- Carte de profil -->
                </div>
            </div>

            <!-- Chat (à droite) -->
            <div
                v-if="selectedProfile"
                class="w-full lg:w-2/3 bg-white rounded-xl shadow-md overflow-hidden"
            >
                <!-- Header -->
                <div class="border-b border-gray-200 p-4">
                    <!-- Informations du profil sélectionné -->
                </div>

                <!-- Messages -->
                <div class="chat-container" ref="chatContainer">
                    <div
                        v-for="message in currentMessages"
                        :class="`flex space-x-2 ${
                            message.isOutgoing ? 'justify-end' : ''
                        }`"
                    >
                        <!-- Affichage des messages -->
                    </div>
                </div>

                <!-- Input de message -->
                <div class="border-t border-gray-200 p-4">
                    <input v-model="newMessage" @keyup.enter="sendMessage" />
                    <button @click="sendMessage">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

            <!-- État "aucun profil sélectionné" -->
            <div
                v-else
                class="w-full lg:w-2/3 bg-white rounded-xl shadow-md p-8"
            >
                <h3>Sélectionnez un profil pour discuter</h3>
            </div>
        </div>
    </MainLayout>
</template>
```

**Points importants à noter :**

-   L'interface est divisée en deux colonnes : la liste des profils à gauche et la conversation à droite.
-   Le profil sélectionné est mis en évidence avec une bordure de couleur.
-   La section de chat n'apparaît que lorsqu'un profil est sélectionné.

### 7.2 Logique du composant

La partie script du composant gère la logique interactive :

```javascript
<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import MainLayout from '@client/Layouts/MainLayout.vue';
import axios from 'axios';
import Echo from 'laravel-echo';

const props = defineProps({
    profiles: { type: Array, default: () => [] }
});

// État des données
const selectedProfile = ref(null);
const newMessage = ref('');
const messagesMap = ref({});  // Map des messages par profileId
const chatContainer = ref(null);
const loading = ref(false);

// Messages pour la conversation courante
const currentMessages = computed(() => {
    if (!selectedProfile.value) return [];
    return messagesMap.value[selectedProfile.value.id] || [];
});

// Sélectionner un profil et charger les messages
async function selectProfile(profile) {
    if (selectedProfile.value && selectedProfile.value.id === profile.id) return;

    selectedProfile.value = profile;

    // Charger les messages si nous ne les avons pas déjà
    if (!messagesMap.value[profile.id]) {
        await loadMessages(profile.id);
    }

    // Faire défiler le chat vers le bas
    nextTick(() => scrollToBottom());
}

// Charger les messages d'un profil
async function loadMessages(profileId) {
    try {
        loading.value = true;
        const response = await axios.get('/messages', {
            params: { profile_id: profileId }
        });

        if (response.data.messages) {
            messagesMap.value[profileId] = response.data.messages;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des messages:', error);
    } finally {
        loading.value = false;
    }
}

// Envoyer un message
async function sendMessage() {
    if (newMessage.value.trim() === '' || !selectedProfile.value) return;

    const profileId = selectedProfile.value.id;
    const messageContent = newMessage.value;

    // Réinitialiser l'input
    newMessage.value = '';

    try {
        const response = await axios.post('/send-message', {
            profile_id: profileId,
            content: messageContent
        });

        // Si le message est envoyé avec succès
        if (response.data.success) {
            // Ajouter le message localement (pour plus de réactivité)
            if (!messagesMap.value[profileId]) {
                messagesMap.value[profileId] = [];
            }

            messagesMap.value[profileId].push(response.data.messageData);

            // Faire défiler le chat vers le bas
            nextTick(() => scrollToBottom());
        }
    } catch (error) {
        console.error('Erreur lors de l\'envoi du message:', error);
    }
}

// Configuration de Laravel Echo
onMounted(() => {
    // Écouter les messages entrants sur le canal privé du client
    if (window.Echo) {
        window.Echo.private(`client.${window.clientId}`)
            .listen('.message.sent', (data) => {
                const profileId = data.profile_id;

                // Formater le message reçu
                const message = {
                    id: data.id,
                    content: data.content,
                    isOutgoing: data.is_from_client,
                    time: new Date(data.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                };

                // Ajouter le message à la conversation
                if (!messagesMap.value[profileId]) {
                    messagesMap.value[profileId] = [];
                }

                messagesMap.value[profileId].push(message);

                // Si c'est la conversation actuelle, faire défiler
                if (selectedProfile.value && selectedProfile.value.id === profileId) {
                    nextTick(() => scrollToBottom());
                }
            });
    }
});
</script>
```

**Points importants à noter :**

1. **État et réactivité :**

    - Nous utilisons `ref` et `computed` pour créer des propriétés réactives.
    - `messagesMap` stocke les messages par ID de profil pour éviter de recharger les conversations.
    - `currentMessages` est une propriété calculée qui renvoie les messages du profil sélectionné.

2. **Sélection de profil :**

    - La fonction `selectProfile` définit le profil sélectionné et charge les messages.
    - Nous ne rechargeons les messages que si la conversation n'a pas encore été chargée.

3. **Gestion des messages :**

    - `loadMessages` récupère les messages existants via API.
    - `sendMessage` envoie un nouveau message et l'ajoute localement pour plus de réactivité.
    - `scrollToBottom` fait défiler automatiquement le chat pour afficher les nouveaux messages.

4. **Réception en temps réel :**
    - Nous écoutons l'événement `.message.sent` sur le canal privé du client.
    - Lorsqu'un message est reçu, nous l'ajoutons à la conversation correspondante.
    - Si la conversation est actuellement affichée, nous faisons défiler vers le bas.

## 8. Flux de Travail Complet

Voici comment fonctionne le système de messagerie client dans son ensemble :

1. Le client se connecte à l'application et accède à la page d'accueil (Home.vue).
2. L'application récupère la liste des profils et les affiche.
3. Le client sélectionne un profil pour discuter.
4. L'application charge les messages existants pour cette conversation.
5. Le client peut envoyer des messages qui sont :
    - Enregistrés en base de données
    - Diffusés via WebSockets au modérateur gérant ce profil
6. Lorsqu'un modérateur répond, son message est diffusé en temps réel au client.
7. L'interface s'actualise automatiquement pour afficher les nouveaux messages.

Ce système offre une expérience de chat fluide et réactive, similaire à celle d'une application de messagerie moderne.

## 9. Considérations de Sécurité

Plusieurs mesures de sécurité ont été mises en place :

1. **Authentification des canaux WebSocket :**

    - Les canaux privés (`private-client.{id}`) nécessitent une authentification.
    - Les autorisations sont vérifiées côté serveur dans `channels.php`.

2. **Contrôle d'accès aux messages :**

    - Un client ne peut accéder qu'à ses propres messages.
    - Les requêtes API sont protégées par le middleware d'authentification.

3. **Validation des entrées :**
    - Toutes les entrées utilisateur sont validées côté serveur.
    - Les ID de profil sont vérifiés pour s'assurer qu'ils existent.

## 10. Améliorations Futures

Pour enrichir davantage le système, les fonctionnalités suivantes pourraient être implémentées :

1. **Indicateurs de statut :**

    - Afficher quand l'autre utilisateur est en train d'écrire.
    - Indiquer quand un message a été lu.

2. **Médias enrichis :**

    - Permettre l'envoi d'images et d'autres médias.
    - Prendre en charge les emojis et le formatage du texte.

3. **Notifications :**

    - Notifier le client lorsqu'il reçoit un message alors qu'il n'est pas sur la page de chat.
    - Ajouter un compteur de messages non lus sur les profils.

4. **Optimisation des performances :**
    - Implémenter la pagination pour les conversations longues.
    - Mettre en cache les conversations fréquemment consultées.

## 11. Améliorations de l'Interface Utilisateur

### 11.1 Nouvelle Organisation des Profils

L'interface a été réorganisée pour offrir une meilleure expérience utilisateur :

1. **Section Découverte (Haut de page) :**

    - Affichage en grille responsive (2-6 colonnes selon la taille d'écran)
    - Pagination avec 12 profils par page
    - Filtres : Tous, En ligne, Nouveaux, Populaires
    - Badges visuels pour les profils en ligne et nouveaux
    - Navigation intuitive entre les pages

2. **Section Conversations (Gauche) :**
    - Affichage des conversations actives uniquement
    - Tri par date du dernier message
    - Indicateurs de messages non lus
    - Gestion des points intégrée
    - Possibilité d'acheter des points pour soi ou pour un interlocuteur

### 11.2 Système de Filtres et Pagination

Le nouveau système de découverte des profils inclut :

1. **Filtres :**

    - **Tous** : Affiche tous les profils disponibles
    - **En ligne** : Montre uniquement les profils actuellement connectés
    - **Nouveaux** : Affiche les profils créés dans les 7 derniers jours
    - **Populaires** : Trie les profils selon leur popularité

2. **Pagination :**
    - 12 profils par page (2 rangées de 6 en mode desktop)
    - Navigation par numéros de page
    - Boutons Précédent/Suivant
    - Indicateur de page courante

### 11.3 Gestion des Points

La gestion des points a été améliorée et intégrée dans la section des conversations :

1. **Affichage des Points :**

    - Points disponibles clairement visibles
    - Bouton d'achat rapide de points
    - Section dédiée pour offrir des points à l'interlocuteur

2. **Actions sur les Points :**
    - Achat de points pour soi-même
    - Don de points à un interlocuteur
    - Alertes de points insuffisants

### 11.4 Interaction avec les Profils

L'interaction avec les profils a été enrichie :

1. **Modal d'Actions :**

    - Vue détaillée du profil
    - Option pour voir la photo en plein écran
    - Option pour démarrer une conversation
    - Informations détaillées sur le profil

2. **Indicateurs Visuels :**
    - Badge "En ligne" pour les profils actifs
    - Badge "Nouveau" pour les profils récents
    - Indicateurs de messages non lus
    - État de frappe en temps réel

## 12. Bonnes Pratiques Implémentées

1. **Performance :**

    - Pagination pour gérer les grandes listes de profils
    - Chargement optimisé des images
    - Mise en cache des données filtrées

2. **UX/UI :**

    - Design responsive
    - Feedback visuel immédiat
    - Navigation intuitive
    - Transitions fluides

3. **Maintenance :**
    - Code modulaire avec composants réutilisables
    - Props et événements bien définis
    - Documentation claire des fonctionnalités

## 13. Prochaines Étapes Possibles

1. **Améliorations Futures :**

    - Recherche avancée de profils
    - Filtres personnalisables
    - Système de favoris
    - Historique de navigation

2. **Optimisations :**
    - Lazy loading des images
    - Infinite scroll comme alternative à la pagination
    - Mise en cache plus sophistiquée
    - Animations plus élaborées

Cette nouvelle version de l'interface combine efficacité et ergonomie, tout en gardant la simplicité d'utilisation qui fait le succès des applications de rencontre modernes.

---

Cette documentation devrait vous aider à comprendre comment les différentes parties du système de messagerie client fonctionnent ensemble. Vous pouvez vous en servir comme référence pour implémenter des fonctionnalités similaires dans d'autres projets.
