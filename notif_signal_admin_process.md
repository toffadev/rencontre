# Documentation: Implémentation des Notifications Admin pour les Signalements de Profils

## Vue d'ensemble

Cette documentation explique l'implémentation d'un système de notifications en temps réel pour les administrateurs lorsqu'un profil est signalé dans une application Laravel 11 avec Vue 3 et Inertia.js.

## Structure du Système

### 1. Tables de Base de Données

#### a. Table `notifications`

Laravel crée automatiquement cette table avec la migration suivante :

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

Cette table est essentielle car elle :

-   Stocke toutes les notifications du système
-   Utilise un UUID comme identifiant unique
-   Le champ `type` indique la classe de notification (ex: NewProfileReport)
-   `notifiable_type` et `notifiable_id` identifient le destinataire
-   `data` contient les informations de la notification en JSON
-   `read_at` trace quand la notification a été lue

Pour créer cette table, exécutez :

```bash
php artisan notifications:table
php artisan migrate
```

#### b. Table `profile_reports`

La table `profile_reports` stocke les informations des signalements :

```php
Schema::create('profile_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->foreignId('reported_profile_id')->constrained('profiles')->onDelete('cascade');
    $table->string('reason');
    $table->text('description')->nullable();
    $table->enum('status', ['pending', 'accepted', 'dismissed'])->default('pending');
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamps();

    // Un utilisateur ne peut signaler un même profil qu'une seule fois
    $table->unique(['reporter_id', 'reported_profile_id']);
});
```

### 2. Configuration du Broadcasting

Dans `config/broadcasting.php`, assurez-vous d'avoir :

```php
'default' => env('BROADCAST_DRIVER', 'pusher'),

'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'encrypted' => true,
        ],
    ],
]
```

### 3. Configuration des Events

Dans `app/Providers/BroadcastServiceProvider.php` :

```php
public function boot()
{
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    require base_path('routes/channels.php');
}
```

Dans `routes/channels.php` :

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

### 4. Classe de Notification (NewProfileReport.php)

Cette classe gère la structure et le format des notifications :

```php
class NewProfileReport extends Notification implements ShouldQueue
{
    protected $report;

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // Stockage en DB et diffusion en temps réel
    }

    public function toDatabase($notifiable)
    {
        // Format pour stockage en base de données
        return [
            'report_id' => $this->report->id,
            'reporter_name' => $this->report->reporter->name,
            // ... autres données
        ];
    }

    public function toBroadcast($notifiable)
    {
        // Format pour diffusion en temps réel
        return new BroadcastMessage([
            'report_id' => $this->report->id,
            // ... données simplifiées
        ]);
    }
}
```

### 5. Intégration avec le Modèle User

Dans `app/Models/User.php`, assurez-vous d'avoir :

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
    // ... reste du code ...
}
```

### 6. Envoi des Notifications

Dans votre contrôleur de signalement (`ProfileReportController.php`) :

```php
public function store(Request $request)
{
    // ... validation et création du signalement ...

    $report = ProfileReport::create($validatedData);

    // Notifier tous les administrateurs
    $admins = User::where('role', 'admin')->get();
    Notification::send($admins, new NewProfileReport($report));

    return response()->json(['message' => 'Signalement créé avec succès']);
}
```

### 7. Configuration des Files d'Attente

Dans `.env` :

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Démarrez le worker de queue :

```bash
php artisan queue:work --queue=notifications
```

### 8. Composant Vue pour les Notifications (NotificationDropdown.vue)

Un composant réutilisable qui :

-   Affiche un compteur de notifications non lues
-   Liste les notifications dans un menu déroulant
-   Permet de marquer les notifications comme lues
-   Met à jour en temps réel via Laravel Echo

Points clés du composant :

```javascript
// Gestion de l'état
const notifications = ref([]);
const unreadCount = ref(0);

// Écoute des nouvelles notifications
window.Echo.private("App.Models.User." + window.userId).notification(
    (notification) => {
        if (notification.type === "App\\Notifications\\NewProfileReport") {
            notifications.value.unshift({
                // ... ajout de la nouvelle notification
            });
            unreadCount.value++;
        }
    }
);
```

## Flux de Données

1. **Signalement Initial**

    - Un utilisateur signale un profil
    - Le système crée un enregistrement dans `profile_reports`
    - Une notification est créée via `NewProfileReport`

2. **Diffusion en Temps Réel**

    - Laravel diffuse la notification via WebSockets
    - Laravel Echo dans le frontend écoute les nouveaux événements
    - Le composant NotificationDropdown met à jour son état

3. **Interface Administrateur**
    - Les administrateurs voient le compteur se mettre à jour
    - Ils peuvent accéder aux détails via le menu déroulant
    - Les actions (marquer comme lu, voir les détails) sont disponibles

## Points Importants à Comprendre

### 1. Temps Réel avec Laravel Echo

-   Utilise WebSockets pour la communication instantanée
-   Nécessite une configuration de Pusher ou Laravel WebSockets
-   Les canaux privés assurent la sécurité des notifications

### 2. File d'Attente (Queue)

-   Les notifications sont mises en file d'attente (`ShouldQueue`)
-   Évite les ralentissements lors de l'envoi de notifications
-   Nécessite la configuration d'un worker Queue

### 3. Sécurité

-   Middleware 'admin' protège les routes
-   Canaux privés pour les WebSockets
-   Vérification des autorisations dans les contrôleurs

### 4. Interface Utilisateur

-   Design responsive et accessible
-   Indicateurs visuels clairs (compteur, états non lus)
-   Actions contextuelles (marquer comme lu, voir détails)

### 5. Gestion des Erreurs

Ajoutez une gestion des erreurs robuste :

```php
try {
    Notification::send($admins, new NewProfileReport($report));
} catch (\Exception $e) {
    Log::error('Erreur lors de l\'envoi de la notification : ' . $e->getMessage());
    // Gérer l'erreur de manière appropriée
}
```

### 6. Maintenance et Nettoyage

Planifiez le nettoyage des anciennes notifications :

```php
// Dans App\Console\Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('notifications:clean')->daily();
}
```

Créez une commande personnalisée :

```php
php artisan make:command CleanNotifications
```

## Bonnes Pratiques

1. **Organisation du Code**

    - Séparation des responsabilités (notifications, contrôleurs, vues)
    - Utilisation de composants réutilisables
    - Code commenté et maintenable

2. **Performance**

    - Mise en file d'attente des notifications
    - Pagination des résultats
    - Optimisation des requêtes avec eager loading

3. **Expérience Utilisateur**
    - Mises à jour en temps réel
    - Interface intuitive
    - Retours visuels immédiats

## Dépendances Requises

```json
{
    "laravel/framework": "^11.0",
    "inertiajs/inertia-laravel": "^1.0",
    "@inertiajs/vue3": "^1.0",
    "vue": "^3.0",
    "tailwindcss": "^3.0"
}
```

## Configuration Nécessaire

1. **Laravel Echo dans resources/js/bootstrap.js**

```javascript
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Echo = new Echo({
    broadcaster: "pusher",
    key: process.env.MIX_PUSHER_APP_KEY,
    // ... autres configurations
});
```

2. **Configuration .env**

```env
BROADCAST_DRIVER=pusher
QUEUE_CONNECTION=redis
PUSHER_APP_ID=votre_id
PUSHER_APP_KEY=votre_clé
PUSHER_APP_SECRET=votre_secret
```

## Tests

Exemple de test pour les notifications :

```php
public function test_admin_receives_notification_when_profile_is_reported()
{
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $report = ProfileReport::factory()->create();

    Notification::assertSentTo(
        [$admin], NewProfileReport::class
    );
}
```

## Débogage

Pour déboguer les notifications :

1. Vérifiez les logs Laravel (`storage/logs/laravel.log`)
2. Utilisez l'outil de débogage de Pusher
3. Inspectez la table `notifications` dans la base de données
4. Vérifiez les logs de la queue worker

## Conclusion

Cette implémentation fournit un système robuste de notifications en temps réel pour les administrateurs. Elle combine les meilleures pratiques de Laravel et Vue.js pour créer une expérience utilisateur fluide et réactive.

## Ressources Utiles

-   [Documentation Laravel Notifications](https://laravel.com/docs/11.x/notifications)
-   [Documentation Laravel Broadcasting](https://laravel.com/docs/11.x/broadcasting)
-   [Documentation Vue 3](https://vuejs.org/guide/introduction.html)
-   [Documentation Inertia.js](https://inertiajs.com/)
