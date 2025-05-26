# Documentation du Système de Gestion des Points

## Table des matières

1. [Introduction](#introduction)
2. [Structure de la Base de Données](#structure-de-la-base-de-données)
3. [Service de Gestion des Points](#service-de-gestion-des-points)
4. [Intégration avec le Système de Messagerie](#intégration-avec-le-système-de-messagerie)
5. [Intégration avec Stripe](#intégration-avec-stripe)
6. [Système de Signalement des Profils](#système-de-signalement-des-profils)
7. [Gestion des Erreurs](#gestion-des-erreurs)

## Introduction

Le système de gestion des points est un mécanisme qui permet aux utilisateurs d'acheter et de dépenser des points pour diverses actions sur la plateforme. Dans notre cas, les points sont principalement utilisés pour envoyer des messages.

### Fonctionnalités principales

-   Achat de points via Stripe
-   Consommation de points pour envoyer des messages
-   Historique des transactions et des consommations
-   Points bonus à l'inscription
-   Gestion des erreurs et des cas limites

## Structure de la Base de Données

### Table `users`

```php
Schema::create('users', function (Blueprint $table) {
    // ... autres champs ...
    $table->integer('points')->default(0);  // Solde de points de l'utilisateur
});
```

### Table `point_transactions`

```php
Schema::create('point_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['purchase', 'initial_bonus', 'system_bonus', 'refund']);
    $table->integer('points_amount');
    $table->decimal('money_amount', 10, 2)->nullable();
    $table->string('stripe_payment_id')->nullable();
    $table->string('stripe_session_id')->nullable();
    $table->string('description')->nullable();
    $table->string('status')->default('completed');
    $table->timestamps();
});
```

### Table `point_consumptions`

```php
Schema::create('point_consumptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['message_sent']);
    $table->integer('points_spent');
    $table->string('description')->nullable();
    $table->morphs('consumable');  // Pour lier à différents types (messages, etc.)
    $table->timestamps();
});
```

## Service de Gestion des Points (PointService)

Le service `PointService` est le cœur du système. Il gère toutes les opérations liées aux points.

### Constantes importantes

```php
const POINTS_PER_MESSAGE = 2;      // Coût en points par message
const INITIAL_BONUS_POINTS = 20;   // Points bonus à l'inscription
```

### Méthodes principales

#### 1. Vérification du solde

```php
public function hasEnoughPoints(User $user, int $points): bool
{
    return $user->points >= $points;
}
```

#### 2. Ajout de points

```php
public function addPoints(User $user, int $points, array $transactionData): PointTransaction
{
    return DB::transaction(function () use ($user, $points, $transactionData) {
        $user->increment('points', $points);
        return PointTransaction::create([...]);
    });
}
```

#### 3. Déduction de points

```php
public function deductPoints(User $user, string $type, int $points, $consumable = null): bool
{
    if (!$this->hasEnoughPoints($user, $points)) {
        return false;
    }

    try {
        DB::transaction(function () {
            // Déduire les points
            // Enregistrer la consommation
        });
        return true;
    } catch (Exception $e) {
        Log::error('Erreur lors de la déduction des points', [...]);
        return false;
    }
}
```

## Intégration avec le Système de Messagerie

### Dans MessageController

Le contrôleur de messages (`MessageController`) intègre la gestion des points dans le processus d'envoi de messages.

```php
public function sendMessage(Request $request)
{
    try {
        // 1. Créer d'abord le message
        $message = Message::create([...]);

        // 2. Déduire les points
        if (!$this->pointService->deductPoints($user, 'message_sent', PointService::POINTS_PER_MESSAGE, $message)) {
            $message->delete();
            return response()->json([
                'error' => 'Points insuffisants',
                'remaining_points' => $user->points
            ], 403);
        }

        // 3. Envoyer les événements
        broadcast(new MessageSent($message));
        event(new NewClientMessage($message));

        // 4. Retourner la réponse
        return response()->json([
            'success' => true,
            'messageData' => [...],
            'remaining_points' => $user->points
        ]);
    } catch (Exception $e) {
        // Gestion des erreurs
    }
}
```

### Flux d'envoi d'un message

1. L'utilisateur tente d'envoyer un message
2. Le système vérifie le solde de points
3. Le message est créé en base de données
4. Les points sont déduits et la consommation est enregistrée
5. Si la déduction échoue, le message est supprimé
6. Les événements sont déclenchés pour notifier les autres parties du système

## Intégration avec Stripe

### Configuration

Dans le fichier `.env` :

```
STRIPE_KEY=pk_test_votre_cle_publique
STRIPE_SECRET=sk_test_votre_cle_secrete
```

### Packs de points disponibles

```javascript
const pointsPlans = [
    {
        points: 100,
        price: "2.99€",
        messages: "20 messages",
    },
    {
        points: 500,
        price: "9.99€",
        messages: "100 messages",
    },
    {
        points: 1000,
        price: "16.99€",
        messages: "200 messages",
    },
];
```

### Processus d'achat

1. L'utilisateur sélectionne un pack de points
2. Une session Stripe est créée
3. L'utilisateur est redirigé vers la page de paiement Stripe
4. Après paiement réussi, les points sont crédités
5. Une transaction est enregistrée

## Système de Signalement des Profils

Le système de signalement permet aux utilisateurs de signaler des profils problématiques. Une fois un profil signalé, il ne sera plus visible pour l'utilisateur qui l'a signalé jusqu'à ce que l'administration traite le signalement.

### Structure de la Base de Données

#### Table `profile_reports`

```php
Schema::create('profile_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
    $table->string('reason');
    $table->text('description')->nullable();
    $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending');
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamps();

    // Un utilisateur ne peut signaler un même profil qu'une seule fois
    $table->unique(['reporter_id', 'reported_user_id']);
});
```

#### Table `notifications`

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->morphs('notifiable');
    $table->text('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
});
```

### Fonctionnalités

1. **Signalement de profil**

    - Les utilisateurs peuvent signaler un profil une seule fois
    - Plusieurs raisons de signalement prédéfinies sont disponibles
    - Une description optionnelle peut être ajoutée

2. **Filtrage des profils**

    - Les profils signalés sont automatiquement masqués pour l'utilisateur qui les a signalés
    - L'état des signalements peut être consulté via l'API

3. **Notifications**
    - Les administrateurs reçoivent une notification par email et dans l'interface d'administration
    - Les notifications incluent les détails du signalement et des liens directs vers les profils concernés

### Routes API

```php
Route::middleware(['auth'])->group(function () {
    Route::post('/profile-reports', [ProfileReportController::class, 'store']);
    Route::get('/blocked-profiles', [ProfileReportController::class, 'getBlockedProfiles']);
});
```

### Intégration dans l'Interface Client

Le système de signalement est intégré directement dans la page d'accueil des clients (`resources/js/Client/Pages/Home.vue`). Chaque carte de profil inclut un bouton de signalement qui, lorsqu'il est cliqué, ouvre un modal permettant de soumettre un signalement.

#### Composants Frontend

1. **Bouton de signalement**

```vue
<button
    @click.stop="showReportModal(profile)"
    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
    title="Signaler ce profil"
>
    <i class="fas fa-flag"></i>
</button>
```

2. **Modal de signalement**

```vue
<ProfileReportModal
    :show="showReportModalFlag"
    :user-id="selectedProfileForReport"
    @close="closeReportModal"
    @reported="handleReported"
/>
```

### Gestion des Profils Bloqués

Le système maintient une liste des profils bloqués pour chaque utilisateur :

```javascript
// Filtrer les profils bloqués
const filteredProfiles = computed(() => {
    return profiles.filter(
        (profile) => !blockedProfileIds.value.includes(profile.id)
    );
});
```

### Workflow de Signalement

1. L'utilisateur clique sur le bouton de signalement d'un profil
2. Le modal de signalement s'ouvre
3. L'utilisateur sélectionne une raison et ajoute optionnellement une description
4. Le signalement est envoyé au serveur
5. Le profil est masqué pour l'utilisateur
6. Les administrateurs reçoivent une notification
7. Le profil reste masqué jusqu'à ce que l'administration traite le signalement

## Gestion des Erreurs

### Types d'erreurs gérées

1. Points insuffisants
2. Erreurs de transaction DB
3. Erreurs de paiement Stripe
4. Erreurs de création de message

### Logging

Toutes les opérations importantes sont loggées :

-   Création de messages
-   Transactions de points
-   Erreurs de paiement
-   Erreurs de déduction de points

### Réponses d'erreur

Les erreurs sont retournées avec des codes HTTP appropriés :

-   403 : Points insuffisants
-   500 : Erreurs serveur
-   400 : Données invalides

## Routes

### Routes pour les points

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/points/data', 'PointController@getPointsData');
    Route::post('/points/checkout', 'PointController@createCheckoutSession');
    Route::get('/points/success', 'PointController@success');
    Route::post('/stripe/webhook', 'PointController@handleWebhook')->withoutMiddleware(['csrf']);
});
```

### Routes pour les messages

```php
Route::middleware(['client_or_admin'])->group(function () {
    Route::get('/messages', 'MessageController@getMessages');
    Route::post('/send-message', 'MessageController@sendMessage');
});
```

## Bonnes Pratiques

1. **Transactions DB** : Toutes les opérations sur les points sont effectuées dans des transactions pour garantir la cohérence des données.

2. **Validation** : Les entrées utilisateur sont toujours validées avant traitement.

3. **Logging** : Des logs détaillés sont maintenus pour le débogage et l'audit.

4. **Gestion des erreurs** : Toutes les opérations sont entourées de try/catch pour une gestion gracieuse des erreurs.

5. **Atomicité** : Les opérations comme l'envoi de message et la déduction de points sont atomiques - soit tout réussit, soit rien ne se passe.
