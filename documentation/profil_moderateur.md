# Documentation - Profil Modérateur

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Structure de la base de données](#structure-de-la-base-de-données)
3. [Backend (Laravel)](#backend-laravel)
4. [Frontend (Vue.js)](#frontend-vuejs)
5. [Tâches planifiées](#tâches-planifiées)
6. [Configuration du projet](#configuration-du-projet)

## Vue d'ensemble

Cette documentation détaille l'implémentation d'une interface de profil pour les modérateurs, permettant de :

-   Visualiser les statistiques de performance
-   Suivre les gains en temps réel
-   Consulter l'historique des messages
-   Analyser les tendances via des graphiques

### Technologies utilisées

-   Backend : Laravel 11
-   Frontend : Vue 3 avec Inertia.js
-   Base de données : MySQL
-   Graphiques : Chart.js
-   Styles : Tailwind CSS

## Structure de la base de données

### Table `moderator_statistics`

Cette table stocke les statistiques quotidiennes des modérateurs :

```sql
CREATE TABLE moderator_statistics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    profile_id BIGINT,
    short_messages_count INT DEFAULT 0,
    long_messages_count INT DEFAULT 0,
    points_received INT DEFAULT 0,
    earnings DECIMAL(10,2) DEFAULT 0,
    stats_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (profile_id) REFERENCES profiles(id),
    INDEX idx_user_date (user_id, stats_date),
    INDEX idx_profile_date (profile_id, stats_date)
);
```

#### Explication des champs :

-   `user_id` : ID du modérateur
-   `profile_id` : ID du profil utilisé
-   `short_messages_count` : Nombre de messages courts (<10 caractères)
-   `long_messages_count` : Nombre de messages longs (≥10 caractères)
-   `points_received` : Points reçus des clients
-   `earnings` : Gains calculés (25 unités par message court, 50 par message long)
-   `stats_date` : Date des statistiques

## Backend (Laravel)

### 1. Modèle

Le modèle `ModeratorStatistic` (`app/Models/ModeratorStatistic.php`) gère les interactions avec la table :

```php
class ModeratorStatistic extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'short_messages_count',
        'long_messages_count',
        'points_received',
        'earnings',
        'stats_date'
    ];

    protected $casts = [
        'stats_date' => 'date',
        'earnings' => 'decimal:2'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    // Accesseurs utiles
    public function getTotalMessagesAttribute()
    {
        return $this->short_messages_count + $this->long_messages_count;
    }

    public function getMessageQualityRateAttribute()
    {
        $total = $this->getTotalMessagesAttribute();
        return $total ? ($this->long_messages_count / $total) * 100 : 0;
    }
}
```

### 2. Contrôleur

Le contrôleur `ModeratorProfileController` gère les requêtes API :

#### Méthodes principales :

1. `index()` : Affiche la page principale

```php
public function index()
{
    return Inertia::render('Moderator/Profile');
}
```

2. `getStatistics()` : Récupère les statistiques filtrées

```php
public function getStatistics(Request $request)
{
    $dateRange = $request->input('dateRange', 'week');
    $profileId = $request->input('profileId');
    $userId = Auth::id();

    // Déterminer la période
    $startDate = match ($dateRange) {
        'day' => now()->startOfDay(),
        'week' => now()->startOfWeek(),
        'month' => now()->startOfMonth(),
        'year' => now()->startOfYear(),
        default => now()->startOfWeek()
    };

    // Construire la requête et retourner les statistiques
    // avec les totaux et moyennes quotidiennes
}
```

3. `getMessageHistory()` : Historique des messages paginé avec statistiques

```php
public function getMessageHistory(Request $request)
{
    $userId = Auth::id();
    $limit = $request->input('limit', 50);
    $profileId = $request->input('profileId');
    $dateRange = $request->input('dateRange', 'week');

    // Déterminer la période comme dans getStatistics()

    // Requête avec filtres de date et de profil
    $query = Message::where('moderator_id', $userId)
        ->where('created_at', '>=', $startDate)
        ->with(['profile:id,name,main_photo_path', 'client:id,name'])
        ->orderBy('created_at', 'desc');

    // Pagination et calcul des statistiques
    return response()->json([
        'messages' => $messages->map(...),
        'statistics' => [
            'total_messages' => $totalMessages,
            'short_messages' => $shortMessages,
            'long_messages' => $longMessages,
            'total_earnings' => $totalEarnings
        ],
        'pagination' => [...]
    ]);
}
```

## Frontend (Vue.js)

### 1. Structure des composants

```
resources/js/Client/Pages/Moderator/
├── Profile.vue                    # Page principale
├── Components/
│   └── FilterBar.vue             # Barre de filtres
└── Partials/
    ├── StatisticsSection.vue     # Section des statistiques
    └── MessageHistorySection.vue  # Section historique messages
```

### 2. Composants détaillés

#### FilterBar.vue

-   Gère les filtres de période et de profil
-   Émet les changements vers le composant parent
-   Utilise Tailwind pour un design responsive

```vue
<script setup>
const props = defineProps({
    dateRange: String,
    profiles: Array,
    showMessageTypeFilter: Boolean,
});

const emit = defineEmits(["filter-changed"]);
</script>
```

#### StatisticsSection.vue

-   Affiche les cartes de statistiques
-   Intègre Chart.js pour les graphiques
-   Met à jour automatiquement les données

```vue
<script setup>
import Chart from "chart.js/auto";

// Configuration du graphique
function initChart() {
    // Configuration Chart.js...
}
</script>
```

#### MessageHistorySection.vue

Le composant gère maintenant de manière autonome :

-   La récupération des messages via l'API
-   Le filtrage par date et profil
-   La pagination
-   Les statistiques locales

```vue
<script setup>
const props = defineProps({
    selectedProfileId: {
        type: [Number, String],
        default: null,
    },
    selectedDateRange: {
        type: String,
        default: "week",
    },
});

// État local
const messages = ref({
    data: [],
    pagination: {
        current_page: 1,
        per_page: 50,
        total: 0,
        last_page: 1,
    },
});

// Statistiques locales
const totalMessages = ref(0);
const shortMessages = ref(0);
const longMessages = ref(0);
const totalEarnings = ref(0);

// Chargement des données avec gestion des filtres
const fetchMessages = async () => {
    try {
        const response = await axios.get("/moderateur/profile/messages", {
            params: {
                dateRange: props.selectedDateRange,
                profileId: props.selectedProfileId,
                limit: messages.value.pagination.per_page,
                page: currentPage.value,
            },
        });

        // Mise à jour des données et statistiques
    } catch (error) {
        console.error("Erreur lors de la récupération des messages:", error);
    }
};

// Surveillance des changements de filtres
watch([() => props.selectedProfileId, () => props.selectedDateRange], () => {
    currentPage.value = 1;
    fetchMessages();
});
</script>
```

#### Profile.vue (Composant parent)

Le composant parent a été simplifié pour :

-   Gérer uniquement les filtres globaux
-   Passer les props nécessaires aux composants enfants
-   Ne plus gérer directement les données des messages

```vue
<script setup>
// État des filtres
const filters = ref({
    dateRange: 'week',
    profileId: '',
    messageType: 'all'
});

// Passage des filtres aux composants
<MessageHistorySection
    :selected-profile-id="filters.profileId"
    :selected-date-range="filters.dateRange"
/>
</script>
```

### 3. Gestion des états

La gestion des états a été réorganisée :

-   Chaque composant gère ses propres données
-   Les filtres sont propagés via props
-   Les composants sont plus autonomes
-   La synchronisation se fait via la surveillance des props

### 4. Formatage des données

Un nouveau module utilitaire a été créé pour centraliser les fonctions de formatage :

```javascript
// resources/js/Client/utils/format.js
export function formatCurrency(value) {
    return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
    }).format(value);
}

export function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString("fr-FR", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}
```

## Tâches planifiées

### 1. Commande personnalisée

`UpdateModeratorStatistics` (`app/Console/Commands/UpdateModeratorStatistics.php`) :

-   Met à jour les statistiques quotidiennes
-   Calcule les gains
-   Agrège les données par profil

```php
class UpdateModeratorStatistics extends Command
{
    protected $signature = 'moderator:update-stats {--date= : Date for which to update statistics}';

    public function handle()
    {
        // Logique de mise à jour...
    }
}
```

### 2. Configuration de la planification

Dans `config/schedule.php` :

```php
return function (Schedule $schedule) {
    $schedule->command('moderator:update-stats')
        ->dailyAt('00:05')
        ->appendOutputTo(storage_path('logs/moderator-stats.log'));
};
```

## Configuration du projet

### 1. Développement local

Le projet utilise `concurrently` pour lancer plusieurs services en parallèle :

```json
{
    "scripts": {
        "serve": "php artisan serve",
        "schedule": "php artisan schedule:work",
        "queue": "php artisan queue:work --daemon",
        "start": "concurrently \"npm run serve\" \"npm run dev\" \"npm run schedule\" \"npm run queue\""
    }
}
```

Pour démarrer le développement :

```bash
npm run start
```

### 2. Points importants à retenir

1. **Sécurité**

    - Middleware `moderator` pour protéger les routes
    - Anonymisation des données clients
    - Validation des entrées utilisateur

2. **Performance**

    - Indexes sur la table des statistiques
    - Pagination des résultats
    - Mise en cache des données statiques

3. **Maintenance**

    - Logs détaillés des mises à jour
    - Tâches planifiées automatisées
    - Structure modulaire pour faciliter les évolutions

4. **UX/UI**
    - Interface responsive
    - Feedback visuel immédiat
    - Filtres intuitifs
    - Graphiques interactifs

## Conclusion

Cette implémentation fournit une interface complète et performante pour les modérateurs, combinant :

-   Statistiques en temps réel
-   Historique détaillé
-   Visualisation des données
-   Automatisation des calculs

La structure modulaire permet d'ajouter facilement de nouvelles fonctionnalités ou de modifier les existantes selon les besoins.
