# Documentation du Système d'Orientation et de Préférences

## Vue d'ensemble

Cette documentation explique en détail la mise en place d'un système de configuration de profil après l'inscription, permettant aux utilisateurs de définir leur orientation sexuelle et leurs préférences de rencontre.

## Table des matières

1. [Structure de la base de données](#structure-de-la-base-de-données)
2. [Modèles](#modèles)
3. [Contrôleurs](#contrôleurs)
4. [Vues](#vues)
5. [Routes](#routes)
6. [Processus de redirection](#processus-de-redirection)
7. [Filtrage des profils](#filtrage-des-profils)

## Structure de la base de données

### Migration client_profiles

Nous avons créé une nouvelle table `client_profiles` avec les champs suivants :

```php
Schema::create('client_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->enum('sexual_orientation', ['heterosexual', 'homosexual'])->nullable();
    $table->enum('seeking_gender', ['male', 'female'])->nullable();
    $table->text('bio')->nullable();
    $table->string('profile_photo_path')->nullable();
    $table->date('birth_date')->nullable();
    $table->string('city')->nullable();
    $table->string('country')->default('France');
    $table->enum('relationship_status', ['single', 'divorced', 'widowed'])->nullable();
    $table->integer('height')->nullable();
    $table->string('occupation')->nullable();
    $table->boolean('has_children')->default(false);
    $table->boolean('wants_children')->nullable();
    $table->boolean('profile_completed')->default(false);
    $table->timestamps();
});
```

Chaque champ a un rôle spécifique :

-   `user_id` : Lie le profil à un utilisateur
-   `sexual_orientation` : Orientation sexuelle (hétérosexuel ou homosexuel)
-   `seeking_gender` : Genre recherché (homme ou femme)
-   `bio` : Description personnelle
-   `profile_photo_path` : Chemin vers la photo de profil
-   Et d'autres informations complémentaires...

## Modèles

### ClientProfile.php

Le modèle gère les interactions avec la table client_profiles :

```php
class ClientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'sexual_orientation',
        'seeking_gender',
        'bio',
        'profile_photo_path',
        // ... autres champs
    ];

    protected $casts = [
        'birth_date' => 'date',
        'has_children' => 'boolean',
        'wants_children' => 'boolean',
        'profile_completed' => 'boolean',
    ];
}
```

### Relation avec User.php

Dans le modèle User, nous avons ajouté la relation :

```php
public function clientProfile()
{
    return $this->hasOne(ClientProfile::class);
}
```

## Contrôleurs

### ProfileSetupController

Ce contrôleur gère la configuration du profil :

```php
class ProfileSetupController extends Controller
{
    public function show()
    {
        // Affiche le formulaire de configuration
        $user = auth()->user();
        $profile = $user->clientProfile;

        return Inertia::render('Client/ProfileSetup', [
            'profile' => $profile,
            'user' => $user
        ]);
    }

    public function store(Request $request)
    {
        // Valide et enregistre les informations du profil
        $validated = $request->validate([
            'sexual_orientation' => 'required|in:heterosexual,homosexual',
            'seeking_gender' => 'required|in:male,female',
            // ... autres validations
        ]);

        // Gestion de la photo de profil
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $validated['profile_photo_path'] = $path;
        }

        // Création ou mise à jour du profil
        ClientProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            array_merge($validated, ['profile_completed' => true])
        );
    }
}
```

### Modification du RegisterController

Le RegisterController a été modifié pour créer un profil initial après l'inscription :

```php
public function register(Request $request)
{
    // ... validation et création de l'utilisateur

    // Création du profil initial
    ClientProfile::create([
        'user_id' => $user->id,
        'birth_date' => $request->dob,
        'profile_completed' => false,
    ]);

    return redirect()->route('profile.setup');
}
```

## Vue (ProfileSetup.vue)

La vue est divisée en trois étapes :

1. Informations de base
2. Préférences
3. Bio

Caractéristiques principales :

-   Design moderne avec Tailwind CSS
-   Formulaire en plusieurs étapes
-   Gestion des photos de profil
-   Validation côté client
-   Transitions fluides entre les étapes

## Routes

```php
Route::middleware(['auth', 'client_only'])->group(function () {
    Route::get('/profile-setup', [ProfileSetupController::class, 'show'])
        ->name('profile.setup');
    Route::post('/profile-setup', [ProfileSetupController::class, 'store'])
        ->name('profile.setup.store');
});
```

## Processus de redirection

1. Après l'inscription, l'utilisateur est redirigé vers `/profile-setup`
2. Une fois le profil complété, il est redirigé vers la page d'accueil
3. Si un utilisateur tente d'accéder à la page d'accueil sans avoir complété son profil, il est redirigé vers la configuration

## Filtrage des profils

Dans le HomeController, les profils sont filtrés en fonction des préférences :

```php
$profiles = Profile::with(['photos', 'mainPhoto', 'user'])
    ->where('status', 'active')
    ->where('gender', $clientProfile->seeking_gender)
    ->latest()
    ->take(10)
    ->get();
```

## Bonnes pratiques implémentées

1. **Validation des données** :

    - Côté serveur avec Laravel
    - Côté client avec Vue.js

2. **Sécurité** :

    - Middleware d'authentification
    - Validation des fichiers uploadés
    - Protection CSRF

3. **UX/UI** :

    - Formulaire en plusieurs étapes
    - Feedback visuel
    - Transitions fluides
    - Design responsive

4. **Performance** :
    - Chargement eager des relations
    - Pagination des résultats
    - Optimisation des requêtes

## Utilisation

Pour utiliser ce système dans un autre projet :

1. Copier les migrations
2. Créer les modèles avec leurs relations
3. Implémenter les contrôleurs
4. Créer les vues
5. Configurer les routes
6. Adapter le design selon vos besoins

## Points d'attention

1. **Validation** : Assurez-vous que tous les champs requis sont validés
2. **Photos** : Gérez correctement le stockage et la taille des images
3. **Performances** : Optimisez les requêtes pour les grandes bases de données
4. **UX** : Testez le formulaire avec différents scénarios d'utilisation
