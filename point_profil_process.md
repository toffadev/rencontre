# Documentation : Système de Gestion des Points pour les Profils

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Structure de la base de données](#structure-de-la-base-de-données)
3. [Modèles](#modèles)
4. [Services](#services)
5. [Contrôleurs](#contrôleurs)
6. [Routes](#routes)
7. [Composants Vue.js](#composants-vuejs)
8. [Événements et Notifications](#événements-et-notifications)
9. [Intégration Stripe](#intégration-stripe)

## Vue d'ensemble

Le système de gestion des points pour les profils permet aux clients d'acheter des points pour interagir avec des profils spécifiques. Ces points sont utilisés pour envoyer des messages et d'autres interactions. Le système comprend :

-   Achat de points via Stripe
-   Attribution automatique des points aux modérateurs
-   Historique des transactions
-   Notifications en temps réel
-   Interface utilisateur intuitive

## Structure de la base de données

### Table : profile_client_interactions

```sql
CREATE TABLE profile_client_interactions (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint unsigned NOT NULL,
    profile_id bigint unsigned NOT NULL,
    moderator_id bigint unsigned NULL,
    last_interaction_at timestamp NULL,
    total_points_received int NOT NULL DEFAULT 0,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (profile_id) REFERENCES profiles(id),
    FOREIGN KEY (moderator_id) REFERENCES users(id)
);
```

### Table : profile_point_transactions

```sql
CREATE TABLE profile_point_transactions (
    id bigint unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint unsigned NOT NULL,
    profile_id bigint unsigned NOT NULL,
    moderator_id bigint unsigned NULL,
    points_amount int NOT NULL,
    money_amount decimal(8,2) NOT NULL,
    status varchar(255) NOT NULL,
    stripe_session_id varchar(255) NULL,
    stripe_payment_id varchar(255) NULL,
    credited_at timestamp NULL,
    created_at timestamp NULL,
    updated_at timestamp NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (profile_id) REFERENCES profiles(id),
    FOREIGN KEY (moderator_id) REFERENCES users(id)
);
```

## Modèles

### ProfileClientInteraction.php

```php
class ProfileClientInteraction extends Model
{
    protected $fillable = [
        'client_id',
        'profile_id',
        'moderator_id',
        'last_interaction_at',
        'total_points_received'
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime'
    ];

    // Relations
    public function client() {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function profile() {
        return $this->belongsTo(Profile::class);
    }

    public function moderator() {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
```

### ProfilePointTransaction.php

```php
class ProfilePointTransaction extends Model
{
    protected $fillable = [
        'client_id',
        'profile_id',
        'moderator_id',
        'points_amount',
        'money_amount',
        'status',
        'stripe_session_id',
        'stripe_payment_id',
        'credited_at'
    ];

    protected $casts = [
        'money_amount' => 'decimal:2',
        'credited_at' => 'datetime'
    ];

    // Relations
    public function client() {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function profile() {
        return $this->belongsTo(Profile::class);
    }

    public function moderator() {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
```

## Services

### ProfilePointService.php

```php
class ProfilePointService
{
    /**
     * Crée une transaction de points pour un profil
     */
    public function createProfilePointTransaction(User $client, Profile $profile, int $points, array $transactionData): ?ProfilePointTransaction
    {
        try {
            return DB::transaction(function () use ($client, $profile, $points, $transactionData) {
                return ProfilePointTransaction::create([
                    'client_id' => $client->id,
                    'profile_id' => $profile->id,
                    'points_amount' => $points,
                    'money_amount' => $transactionData['money_amount'],
                    'stripe_session_id' => $transactionData['stripe_session_id'],
                    'status' => 'pending'
                ]);
            });
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de la transaction', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Traite une transaction réussie
     */
    public function processSuccessfulTransaction(ProfilePointTransaction $transaction): bool
    {
        try {
            return DB::transaction(function () use ($transaction) {
                // Trouver ou créer l'interaction
                $interaction = ProfileClientInteraction::firstOrCreate(
                    [
                        'client_id' => $transaction->client_id,
                        'profile_id' => $transaction->profile_id
                    ],
                    [
                        'total_points_received' => 0
                    ]
                );

                // Mettre à jour les points
                $interaction->total_points_received += $transaction->points_amount;
                $interaction->save();

                // Attribuer les points au modérateur
                if ($interaction->moderator_id) {
                    $moderator = User::find($interaction->moderator_id);
                    if ($moderator) {
                        $moderator->increment('points', $transaction->points_amount);
                        $transaction->moderator_id = $moderator->id;
                    }
                }

                // Finaliser la transaction
                $transaction->status = 'completed';
                $transaction->credited_at = now();
                $transaction->save();

                // Émettre l'événement
                event(new ProfilePointsPurchased($transaction));

                return true;
            });
        } catch (Exception $e) {
            Log::error('Erreur lors du traitement de la transaction', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
```

## Contrôleurs

### ProfilePointController.php

```php
class ProfilePointController extends Controller
{
    protected $profilePointService;

    public function __construct(ProfilePointService $profilePointService)
    {
        $this->profilePointService = $profilePointService;
    }

    /**
     * Crée une session de paiement Stripe
     */
    public function createCheckoutSession(Request $request)
    {
        try {
            $request->validate([
                'profile_id' => 'required|exists:profiles,id',
                'pack' => 'required|string|in:100,500,1000'
            ]);

            $profile = Profile::findOrFail($request->profile_id);
            $pointsPacks = [
                '100' => ['price' => 2.99, 'points' => 100],
                '500' => ['price' => 9.99, 'points' => 500],
                '1000' => ['price' => 16.99, 'points' => 1000]
            ];

            $pack = $pointsPacks[$request->pack];

            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => "{$pack['points']} Points pour {$profile->name}",
                            'description' => "Pack de {$pack['points']} points pour {$profile->name}"
                        ],
                        'unit_amount' => (int)($pack['price'] * 100)
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('profile.points.success') . '?session_id={CHECKOUT_SESSION_ID}&points=' . $pack['points'] . '&profile_id=' . $profile->id,
                'cancel_url' => route('client.profile.points', ['profile' => $profile->id]),
                'metadata' => [
                    'points_amount' => $pack['points'],
                    'client_id' => Auth::id(),
                    'profile_id' => $profile->id
                ]
            ]);

            // Créer la transaction en attente
            $transaction = $this->profilePointService->createProfilePointTransaction(
                Auth::user(),
                $profile,
                $pack['points'],
                [
                    'money_amount' => $pack['price'],
                    'stripe_session_id' => $session->id
                ]
            );

            return response()->json(['sessionId' => $session->id]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de la session', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Une erreur est survenue'], 500);
        }
    }
}
```

## Routes

```php
// Routes pour les points des profils
Route::prefix('profile-points')->name('profile.points.')->group(function () {
    Route::post('/checkout', [ProfilePointController::class, 'createCheckoutSession'])
        ->name('checkout');
    Route::get('/success', [ProfilePointController::class, 'success'])
        ->name('success');
    Route::get('/transactions/profile/{profile}', [ProfilePointController::class, 'getProfileTransactionHistory'])
        ->name('transactions.profile');
    Route::get('/transactions/client', [ProfilePointController::class, 'getClientTransactionHistory'])
        ->name('transactions.client');
});

// Route pour la page des points d'un profil
Route::get('/profile/{profile}/points', function (Profile $profile) {
    return Inertia::render('ProfilePoints', [
        'profile' => $profile,
        'stripeKey' => config('services.stripe.key')
    ]);
})->name('client.profile.points');
```

## Composants Vue.js

### PurchasePoints.vue

```vue
<template>
    <div class="w-full p-4">
        <div class="points-packs grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
                v-for="(pack, key) in pointsPacks"
                :key="key"
                class="pack-card p-4 border rounded-lg shadow-sm hover:shadow-md transition-shadow bg-white"
            >
                <h3 class="text-xl font-semibold mb-2">
                    {{ pack.points }} Points
                </h3>
                <p class="text-gray-600 mb-4">{{ pack.description }}</p>
                <div class="price text-2xl font-bold text-blue-600 mb-4">
                    {{ formatPrice(pack.price) }} €
                </div>
                <button
                    @click="purchasePoints(key)"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition-colors"
                    :disabled="loading"
                >
                    {{ loading ? "Chargement..." : "Acheter" }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
    profileId: {
        type: Number,
        required: true,
    },
    stripeKey: {
        type: String,
        required: true,
    },
});

const loading = ref(false);
const error = ref(null);

const pointsPacks = {
    100: {
        points: 100,
        price: 2.99,
        description: "Pack de démarrage",
    },
    500: {
        points: 500,
        price: 9.99,
        description: "Pack populaire",
    },
    1000: {
        points: 1000,
        price: 16.99,
        description: "Pack premium",
    },
};

const purchasePoints = async (packKey) => {
    if (!window.Stripe) {
        error.value = "Stripe n'est pas initialisé";
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const stripe = window.Stripe(props.stripeKey);

        const response = await axios.post(route("profile.points.checkout"), {
            profile_id: props.profileId,
            pack: packKey,
        });

        const { sessionId } = response.data;

        const result = await stripe.redirectToCheckout({
            sessionId,
        });

        if (result.error) {
            throw new Error(result.error.message);
        }
    } catch (e) {
        error.value =
            e.response?.data?.error ||
            "Une erreur est survenue lors de la transaction";
        console.error("Erreur lors de l'achat:", e);
    } finally {
        loading.value = false;
    }
};
</script>
```

## Configuration

### .env

```
STRIPE_KEY=pk_test_votre_clé_publique
STRIPE_SECRET=sk_test_votre_clé_secrète
STRIPE_WEBHOOK_SECRET=whsec_votre_clé_webhook
```

### config/services.php

```php
'stripe' => [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],
],
```

## Points importants

1. **Sécurité**

    - Toutes les routes sont protégées par authentification
    - Validation des données côté serveur
    - Vérification des signatures Stripe pour les webhooks
    - Protection CSRF sur toutes les routes POST

2. **Gestion des erreurs**

    - Logging détaillé des erreurs
    - Messages d'erreur utilisateur conviviaux
    - Transactions DB pour garantir l'intégrité des données

3. **Workflow de paiement**

    - Création de la transaction en statut 'pending'
    - Redirection vers Stripe pour le paiement
    - Confirmation via webhook
    - Attribution des points au modérateur
    - Mise à jour du statut en 'completed'

4. **Maintenance**
    - Surveiller les logs d'erreur
    - Vérifier les webhooks Stripe
    - Monitorer les transactions échouées
    - Maintenir les dépendances à jour
