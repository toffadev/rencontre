# Documentation : Gestion des Informations Clients

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Structure de la base de données](#structure-de-la-base-de-données)
3. [Interface utilisateur](#interface-utilisateur)
4. [Composants](#composants)
5. [Fonctionnalités clés](#fonctionnalités-clés)
6. [Bonnes pratiques](#bonnes-pratiques)

## Vue d'ensemble

Cette documentation détaille la mise en place d'un système de gestion des informations clients dans une application de rencontre. Le système est conçu pour permettre aux modérateurs de visualiser et gérer les informations des clients tout en discutant avec eux.

### Architecture générale

L'interface est divisée en trois colonnes principales :

-   Liste des clients (gauche, 1/4 de l'écran)
-   Chat (centre, 2/4 de l'écran)
-   Informations client (droite, 1/4 de l'écran)

Cette disposition permet une expérience utilisateur optimale où le chat reste l'élément central tout en gardant les informations clients facilement accessibles.

## Structure de la base de données

### Table `client_infos`

```php
Schema::create('client_infos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->integer('age')->nullable();
    $table->string('ville')->nullable();
    $table->string('quartier')->nullable();
    $table->string('profession')->nullable();
    $table->enum('celibataire', ['oui', 'non'])->nullable();
    $table->enum('situation_residence', ['seul', 'colocation', 'famille', 'autre'])->nullable();
    $table->enum('orientation', ['heterosexuel', 'homosexuel', 'bisexuel'])->nullable();
    $table->string('loisirs')->nullable();
    $table->string('preference_negative')->nullable();
    $table->timestamps();
});
```

Cette structure permet de stocker :

-   Les informations de base (âge, ville, quartier)
-   Le statut professionnel et personnel
-   Les préférences et caractéristiques personnelles
-   Tous les champs sont nullables pour permettre un remplissage progressif

## Interface utilisateur

### Panneau d'informations client (ClientInfoPanel.vue)

Le panneau est divisé en trois sections principales :

1. **Informations de base**

    - Affichage en grille des informations principales
    - Chaque information dans une carte distincte
    - Indicateur visuel quand une information est manquante

2. **Mode édition**

    - Formulaire avec validation
    - Boutons pour modifier/annuler
    - Mise à jour en temps réel

3. **Informations personnalisées**
    - Section pour les notes et informations additionnelles
    - Possibilité d'ajouter/supprimer des informations
    - Horodatage et attribution des modifications

## Composants

### 1. ClientInfoPanel.vue

```vue
<template>
    <div class="bg-white rounded-xl shadow-md p-4 mb-4">
        <!-- En-tête avec titre et bouton de modification -->
        <!-- Section des informations de base -->
        <!-- Formulaire d'édition -->
        <!-- Section des informations personnalisées -->
    </div>
</template>
```

Caractéristiques principales :

-   Design responsive
-   Transitions fluides entre les modes
-   Validation des données
-   Gestion des erreurs
-   Mise à jour en temps réel

### 2. Intégration dans Moderator.vue

```vue
<div class="flex flex-col lg:flex-row gap-6">
    <!-- Liste des clients -->
    <!-- Chat -->
    <!-- Panneau d'informations -->
</div>
```

## Fonctionnalités clés

### 1. Gestion des données

-   Chargement asynchrone des informations
-   Mise en cache locale des données
-   Validation côté client et serveur
-   Gestion des erreurs et retours utilisateur

### 2. Interface utilisateur

-   Design responsive
-   Transitions fluides
-   Feedback visuel immédiat
-   Mode édition intuitif

### 3. Mise à jour en temps réel

-   Synchronisation automatique des données
-   Notifications de modifications
-   État de chargement pour les opérations longues

## Bonnes pratiques

### 1. Structure des composants

-   Séparation claire des responsabilités
-   Composants réutilisables
-   Props et événements bien définis
-   Documentation des props et méthodes

### 2. Gestion des données

-   Validation systématique
-   Gestion des erreurs robuste
-   Mise en cache appropriée
-   Optimisation des performances

### 3. UX/UI

-   Feedback utilisateur clair
-   Transitions fluides
-   Design cohérent
-   Accessibilité

### 4. Sécurité

-   Validation des données
-   Protection CSRF
-   Gestion des permissions
-   Sanitization des entrées

## Conseils d'implémentation

1. **Commencez par la base de données**

    - Définissez clairement votre schéma
    - Prévoyez les relations
    - Pensez à la scalabilité

2. **Structurez vos composants**

    - Créez des composants réutilisables
    - Documentez les props et événements
    - Suivez une convention de nommage

3. **Gérez l'état**

    - Utilisez Vuex ou Pinia si nécessaire
    - Centralisez la logique métier
    - Gardez les composants simples

4. **Optimisez les performances**
    - Lazy loading des composants
    - Mise en cache appropriée
    - Pagination des données

## Backend et API

### Routes (routes/web.php)

```php
// Routes pour la gestion des informations clients
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->group(function () {
    // Informations de base du client
    Route::get('/clients/{client}/info', 'ModeratorController@getClientInfo');
    Route::post('/clients/{client}/basic-info', 'ModeratorController@updateBasicInfo');

    // Informations personnalisées
    Route::post('/clients/{client}/custom-info', 'ModeratorController@addCustomInfo');
    Route::delete('/custom-info/{id}', 'ModeratorController@deleteCustomInfo');

    // Routes pour le chat et la gestion des clients
    Route::get('/clients', 'ModeratorController@getAssignedClients');
    Route::get('/available-clients', 'ModeratorController@getAvailableClients');
    Route::post('/start-conversation', 'ModeratorController@startConversation');
    Route::get('/messages', 'ModeratorController@getMessages');
    Route::post('/send-message', 'ModeratorController@sendMessage');
});
```

### Contrôleur (ModeratorController.php)

```php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClientInfo;
use App\Models\CustomInfo;
use Illuminate\Http\Request;
use App\Events\MessageSent;

class ModeratorController extends Controller
{
    /**
     * Récupérer les informations d'un client
     */
    public function getClientInfo(User $client)
    {
        $basicInfo = ClientInfo::where('user_id', $client->id)->first();
        $customInfos = CustomInfo::with('added_by')
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'basic_info' => $basicInfo,
            'custom_infos' => $customInfos
        ]);
    }

    /**
     * Mettre à jour les informations de base
     */
    public function updateBasicInfo(Request $request, User $client)
    {
        $validated = $request->validate([
            'age' => 'nullable|integer|min:18|max:100',
            'ville' => 'nullable|string|max:255',
            'quartier' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',
            'celibataire' => 'nullable|in:oui,non',
            'situation_residence' => 'nullable|in:seul,colocation,famille,autre',
            'orientation' => 'nullable|in:heterosexuel,homosexuel,bisexuel',
            'loisirs' => 'nullable|string|max:255',
            'preference_negative' => 'nullable|string|max:255'
        ]);

        $clientInfo = ClientInfo::updateOrCreate(
            ['user_id' => $client->id],
            $validated
        );

        return response()->json($clientInfo);
    }

    /**
     * Ajouter une information personnalisée
     */
    public function addCustomInfo(Request $request, User $client)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string'
        ]);

        $customInfo = CustomInfo::create([
            'client_id' => $client->id,
            'added_by_id' => auth()->id(),
            'titre' => $validated['titre'],
            'contenu' => $validated['contenu']
        ]);

        return response()->json($customInfo->load('added_by'));
    }

    /**
     * Supprimer une information personnalisée
     */
    public function deleteCustomInfo($id)
    {
        $customInfo = CustomInfo::findOrFail($id);
        $customInfo->delete();
        return response()->json(['success' => true]);
    }
}
```

### Modèles

#### ClientInfo.php

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientInfo extends Model
{
    protected $fillable = [
        'user_id',
        'age',
        'ville',
        'quartier',
        'profession',
        'celibataire',
        'situation_residence',
        'orientation',
        'loisirs',
        'preference_negative'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

#### CustomInfo.php

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomInfo extends Model
{
    protected $fillable = [
        'client_id',
        'added_by_id',
        'titre',
        'contenu'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function added_by()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
```

## Interactions et Flux de données

### 1. Chargement initial des informations

```javascript
// Dans ClientInfoPanel.vue
const loadClientInfo = async () => {
    try {
        const response = await axios.get(
            `/moderateur/clients/${props.clientId}/info`
        );
        basicInfo.value = response.data.basic_info;
        customInfos.value = response.data.custom_infos;

        // Pré-remplir le formulaire des infos de base
        if (basicInfo.value) {
            basicInfoForm.value = { ...basicInfo.value };
        }
    } catch (error) {
        console.error("Erreur lors du chargement des informations:", error);
    }
};
```

### 2. Mise à jour des informations

```javascript
// Dans ClientInfoPanel.vue
const updateBasicInfo = async () => {
    try {
        await axios.post(
            `/moderateur/clients/${props.clientId}/basic-info`,
            basicInfoForm.value
        );
        await loadClientInfo();
        showBasicInfoForm.value = false;
    } catch (error) {
        console.error("Erreur lors de la mise à jour des informations:", error);
    }
};
```

### 3. Gestion des informations personnalisées

```javascript
// Ajouter une information
const addCustomInfo = async () => {
    try {
        await axios.post(
            `/moderateur/clients/${props.clientId}/custom-info`,
            customInfoForm.value
        );
        await loadClientInfo();
        customInfoForm.value = { titre: "", contenu: "" };
    } catch (error) {
        console.error("Erreur lors de l'ajout de l'information:", error);
    }
};

// Supprimer une information
const deleteInfo = async (infoId) => {
    if (!confirm("Voulez-vous vraiment supprimer cette information ?")) return;

    try {
        await axios.delete(`/moderateur/custom-info/${infoId}`);
        await loadClientInfo();
    } catch (error) {
        console.error("Erreur lors de la suppression de l'information:", error);
    }
};
```

## Middleware et Sécurité

### Middleware Moderator

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ModeratorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || auth()->user()->type !== 'moderateur') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
```

### Protection des routes

-   Middleware d'authentification
-   Vérification du rôle modérateur
-   Protection CSRF
-   Validation des données

## Tests

### Tests unitaires (ModeratorControllerTest.php)

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\ClientInfo;

class ModeratorControllerTest extends TestCase
{
    public function test_can_get_client_info()
    {
        $moderator = User::factory()->create(['type' => 'moderateur']);
        $client = User::factory()->create(['type' => 'client']);

        $this->actingAs($moderator)
            ->getJson("/moderateur/clients/{$client->id}/info")
            ->assertStatus(200)
            ->assertJsonStructure([
                'basic_info',
                'custom_infos'
            ]);
    }

    // Autres tests...
}
```

## Conclusion

Cette implémentation fournit une base solide pour la gestion des informations clients dans une application de rencontre. Elle est conçue pour être :

-   Extensible
-   Maintenable
-   Performante
-   Facile à utiliser

La documentation ci-dessus devrait vous permettre de comprendre la structure et de l'adapter à vos besoins spécifiques.
