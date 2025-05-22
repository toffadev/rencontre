# Documentation du Système de Modération

## 1. Introduction et Vue d'ensemble

Ce document explique le système de modération mis en place dans l'application de rencontres. Le concept principal est le suivant :

-   Les **modérateurs** sont des utilisateurs qui peuvent utiliser des **profils virtuels** pour discuter avec les **clients**.
-   Les clients ne voient pas le modérateur mais uniquement le profil virtuel.
-   Un profil virtuel peut être utilisé par plusieurs modérateurs, mais jamais simultanément.
-   **Le système attribue automatiquement les profils ET les clients aux modérateurs disponibles**.
-   Les discussions entre les profils virtuels et les clients sont visibles par tous les modérateurs qui ont été attribués à ce profil.

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

Nous avons créé deux migrations pour ces tables :

#### Migration pour `moderator_profile_assignments`

```php
Schema::create('moderator_profile_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('profile_id')->constrained()->onDelete('cascade');
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_activity')->nullable();
    $table->timestamps();

    // Un modérateur ne peut avoir qu'un profil actif à la fois
    $table->unique(['user_id', 'is_active'], 'moderator_active_profile');

    // Un profil ne peut être attribué activement qu'à un seul modérateur à la fois
    $table->unique(['profile_id', 'is_active'], 'profile_active_moderator');
});
```

#### Migration pour `messages`

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('profile_id')->constrained()->onDelete('cascade');
    $table->foreignId('moderator_id')->nullable()->constrained('users')->onDelete('set null');
    $table->text('content');
    $table->boolean('is_from_client')->default(true);
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    // Index pour la recherche rapide des conversations
    $table->index(['client_id', 'profile_id']);
});
```

**Points importants à noter :**

-   Les contraintes d'unicité garantissent qu'un modérateur ne peut avoir qu'un profil actif à la fois et qu'un profil ne peut être attribué activement qu'à un seul modérateur à la fois.
-   L'index sur `client_id` et `profile_id` permet de récupérer rapidement les conversations entre un client et un profil.

## 3. Le Service d'Attribution de Profils et Clients

Nous avons créé un service dédié (`ModeratorAssignmentService`) pour gérer l'attribution des profils aux modérateurs et des clients aux modérateurs. Ce service centralise toute la logique d'attribution et offre plusieurs méthodes :

### 3.1 Méthodes principales

#### `assignProfileToModerator(User $moderator, ?Profile $profile = null)`

-   Attribue un profil à un modérateur.
-   Si aucun profil n'est spécifié, le système en sélectionne un automatiquement.
-   Désactive les attributions précédentes du modérateur.
-   Vérifie si le profil est déjà utilisé par un autre modérateur.
-   Déclenche l'événement `ProfileAssigned`.

#### `assignClientToModerator(User $moderator, User $client = null)`

-   Attribue un client à un modérateur.
-   Si aucun client n'est spécifié, le système cherche un client qui a besoin d'une réponse.
-   Priorise les clients qui attendent une réponse depuis longtemps.
-   Assure qu'un client n'est pas attribué à plusieurs modérateurs simultanément.

#### `findAvailableProfile()`

-   Recherche un profil disponible (non attribué actuellement).
-   Sélectionne aléatoirement parmi les profils actifs non attribués.

#### `findClientNeedingResponse()`

-   Recherche un client qui a envoyé un message et attend une réponse.
-   Priorise les messages les plus anciens sans réponse.

#### `releaseProfile(User $moderator)`

-   Libère le profil attribué à un modérateur.

#### `getCurrentAssignedProfile(User $moderator)`

-   Récupère le profil actuellement attribué à un modérateur.

#### `updateLastActivity(User $moderator)`

-   Met à jour l'horodatage de dernière activité d'un modérateur.

#### `reassignInactiveProfiles(int $inactiveMinutes = 30)`

-   Libère les profils des modérateurs inactifs depuis un certain temps.
-   Permet de réattribuer ces profils à d'autres modérateurs.

## 4. Contrôleur pour les Modérateurs

Le `ModeratorController` gère toutes les actions spécifiques aux modérateurs :

### 4.1 Routes

Nous avons défini les routes suivantes dans `routes/web.php` :

```php
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->name('moderator.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');

    // Page principale des modérateurs
    Route::get('/chat', [App\Http\Controllers\Moderator\ModeratorController::class, 'index'])->name('chat');

    // API pour les modérateurs
    Route::get('/clients', [App\Http\Controllers\Moderator\ModeratorController::class, 'getClients'])->name('clients');
    Route::get('/profile', [App\Http\Controllers\Moderator\ModeratorController::class, 'getAssignedProfile'])->name('profile');
    Route::get('/messages', [App\Http\Controllers\Moderator\ModeratorController::class, 'getMessages'])->name('messages');
    Route::post('/send-message', [App\Http\Controllers\Moderator\ModeratorController::class, 'sendMessage'])->name('send-message');
});
```

### 4.2 Méthodes du contrôleur

#### `index()`

-   Affiche la page principale des modérateurs (interface de chat).
-   Vérifie si le modérateur a déjà un profil attribué et met à jour son activité.
-   **Si nécessaire, attribue automatiquement un profil et un client au modérateur.**

#### `getClients()`

-   **Récupère le client actuellement attribué au modérateur.**
-   **Renvoie uniquement le client qui a été attribué par le système au modérateur.**
-   Inclut les informations sur le dernier message et le nombre de messages non lus.

#### `getAssignedProfile()`

-   Récupère le profil actuellement attribué au modérateur.
-   Si aucun profil n'est attribué, tente d'en attribuer un automatiquement.

#### `getMessages(Request $request)`

-   Récupère les messages échangés entre un client spécifique et le profil attribué.
-   Marque les messages non lus comme lus.

#### `sendMessage(Request $request)`

-   Envoie un message d'un modérateur (via le profil attribué) à un client.
-   Met à jour l'activité du modérateur.
-   Déclenche l'événement `MessageSent`.

## 5. Système d'Événements en Temps Réel

Pour permettre la communication en temps réel, nous utilisons Laravel Echo et Pusher. Deux événements principaux ont été créés :

### 5.1 Événement `MessageSent`

-   Déclenché lorsqu'un message est envoyé.
-   Diffuse le message sur deux canaux :
    -   Canal privé du client : `client.{client_id}`
    -   Canal privé du profil : `profile.{profile_id}`
-   Les données diffusées incluent le contenu du message, les IDs du client et du profil, etc.

### 5.2 Événement `ProfileAssigned`

-   Déclenché lorsqu'un profil est attribué à un modérateur.
-   Diffuse sur le canal privé du modérateur : `moderator.{moderator_id}`
-   Les données diffusées incluent les détails du profil attribué.

### 5.3 Événement `ClientAssigned`

-   Déclenché lorsqu'un client est attribué à un modérateur.
-   Diffuse sur le canal privé du modérateur : `moderator.{moderator_id}`
-   Les données diffusées incluent les détails du client attribué et la conversation associée.

## 6. Interface Utilisateur du Modérateur

L'interface utilisateur des modérateurs est définie dans le fichier `resources/js/Client/Pages/Moderator.vue`. Cette interface comprend :

### 6.1 Structure générale

-   Un en-tête indiquant qu'il s'agit de l'espace modérateur.
-   Une section d'attente si aucun profil n'est attribué.
-   Une disposition en deux colonnes :
    -   **Colonne de gauche : affichage du client attribué par le système.**
    -   Colonne de droite :
        -   En haut : informations sur le profil attribué.
        -   En bas : interface de chat avec le client attribué.

### 6.2 Fonctionnalités principales

-   **Affichage du client actuellement attribué par le système.**
-   Affichage du nombre de messages non lus.
-   Interface de chat avec le client attribué.
-   Envoi de messages au client.
-   Affichage en temps réel des nouveaux messages.

### 6.3 Intégration avec Laravel Echo

Le composant Vue est configuré pour écouter les événements en temps réel via Laravel Echo. Cela permet :

-   De recevoir automatiquement les nouveaux messages.
-   D'être notifié lorsqu'un profil est attribué.
-   **D'être notifié lorsqu'un client est attribué.**
-   De mettre à jour l'interface sans rechargement de page.

## 7. Flux de Travail Global

Voici comment fonctionne le système dans son ensemble :

1. Un modérateur se connecte et accède à la page de chat.
2. Le système lui attribue automatiquement un profil disponible (ou utilise celui déjà attribué).
3. **Le système attribue automatiquement un client au modérateur (généralement un client qui a besoin d'une réponse).**
4. **Le modérateur voit uniquement le client qui lui a été attribué.**
5. Le modérateur peut envoyer des messages au client (qui voit ces messages comme venant du profil virtuel).
6. **Une fois la conversation terminée, le système peut attribuer un nouveau client au modérateur.**
7. Si un client envoie un message, tous les modérateurs ayant accès au profil concerné peuvent le voir.
8. Un profil ne peut être utilisé que par un seul modérateur à la fois.
9. Si un modérateur devient inactif, son profil peut être réattribué à un autre modérateur.

## 8. Prochaines Étapes

Pour finaliser l'implémentation, il faudra :

1. Exécuter les migrations pour créer les tables nécessaires.
2. Configurer Laravel Echo côté client pour gérer les événements en temps réel.
3. Compléter l'interface Vue pour qu'elle utilise les API réelles au lieu des données simulées.
4. Créer une interface d'administration pour gérer les profils virtuels.
5. Mettre en place un système de notification pour alerter les modérateurs des nouveaux messages.
6. **Implémenter un algorithme de répartition pour attribuer équitablement les clients aux modérateurs disponibles.**
7. **Mettre en place un système de file d'attente pour les clients en attente de réponse.**

## 9. Considérations de Sécurité

-   Assurez-vous que les canaux de diffusion sont correctement protégés.
-   Vérifiez toujours les autorisations dans les contrôleurs (un modérateur ne doit accéder qu'aux conversations liées au profil qui lui est attribué).
-   Protégez les données sensibles des clients.
-   Mettez en place une politique de modération claire pour les modérateurs.

## 10. Conclusion

Ce système permet une gestion efficace des conversations entre clients et profils virtuels, tout en offrant une expérience transparente pour les clients. La flexibilité du système permet d'attribuer dynamiquement des profils et des clients aux modérateurs, optimisant ainsi l'utilisation des ressources humaines et assurant que tous les clients reçoivent une réponse.

---

Cette documentation devrait vous aider à comprendre la structure et le fonctionnement du système de modération. N'hésitez pas à la compléter au fur et à mesure que vous ajoutez de nouvelles fonctionnalités.
