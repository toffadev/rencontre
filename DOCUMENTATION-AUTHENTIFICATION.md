# Documentation: Système d'Authentification avec Laravel 11, Sanctum, Vue 3 et Inertia

## 1. Vue d'ensemble

Ce document explique l'implémentation d'un système d'authentification complet pour une application de rencontre utilisant:

-   **Laravel 11** comme framework backend
-   **Laravel Sanctum** pour l'authentification basée sur les tokens
-   **Vue 3** pour les composants frontend
-   **Inertia.js** comme pont entre Laravel et Vue
-   **TailwindCSS** pour le styling

Le système prend en charge plusieurs types d'utilisateurs (client, modérateur, admin) et gère l'inscription, la connexion, le mot de passe oublié, et les autorisations basées sur les rôles.

## 2. Structure de la base de données

### Table `users`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['client', 'moderateur', 'admin'])->default('client');
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->integer('points')->default(0);
    $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
    $table->rememberToken();
    $table->timestamps();
});
```

Cette structure stocke:

-   Le type d'utilisateur (client, modérateur, admin)
-   Les informations de base (nom, email)
-   Un système de points pour les clients
-   Un statut pour gérer l'accès

### Migration

Pour ajouter ces champs, nous utilisons une migration Laravel:

```bash
php artisan make:migration add_type_and_points_to_users_table --table=users
```

## 3. Modèle User

Le modèle User étend `Authenticatable` et utilise le trait `HasApiTokens` de Sanctum:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'points',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->type === 'moderateur';
    }

    public function isClient(): bool
    {
        return $this->type === 'client';
    }
}
```

Le trait `HasApiTokens` est crucial car il ajoute les méthodes de gestion des tokens pour Sanctum.

## 4. Configuration de Laravel Sanctum

### Installation

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### Configuration dans bootstrap/app.php (Laravel 11)

Dans Laravel 11, on configure les middlewares directement dans `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    // Base Web Middleware
    $middleware->web(\App\Http\Middleware\HandleInertiaRequests::class);

    // API Middleware
    $middleware->api(Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

    // Named Middleware
    $middleware->alias([
        'auth' => \App\Http\Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
    ]);
})
```

## 5. Middleware d'Authentification

### Authenticate.php

Redirige les utilisateurs non-authentifiés vers la page de connexion:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        return route('login');
    }
}
```

### RedirectIfAuthenticated.php

Redirige les utilisateurs déjà authentifiés vers leurs tableaux de bord respectifs:

```php
<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if ($user->type === 'admin') {
                    return redirect()->route('admin.dashboard');
                } elseif ($user->type === 'moderateur') {
                    return redirect()->route('moderator.dashboard');
                }

                return redirect()->route('home');
            }
        }

        return $next($request);
    }
}
```

### AdminMiddleware.php

Contrôle l'accès aux routes d'administration:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || Auth::user()->type !== 'admin') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Accès non autorisé'], 403);
            }

            return redirect()->route('home')->with('error', 'Vous n\'avez pas la permission d\'accéder à cette page.');
        }

        return $next($request);
    }
}
```

### ModeratorMiddleware.php

Contrôle l'accès aux routes de modération:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ModeratorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || (Auth::user()->type !== 'admin' && Auth::user()->type !== 'moderateur')) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Accès non autorisé'], 403);
            }

            return redirect()->route('home')->with('error', 'Vous n\'avez pas la permission d\'accéder à cette page.');
        }

        return $next($request);
    }
}
```

### HandleInertiaRequests.php

Transmet les données d'authentification à Inertia:

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

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
            'flash' => [
                'message' => fn() => $request->session()->get('message'),
                'error' => fn() => $request->session()->get('error'),
            ],
        ]);
    }
}
```

## 6. Contrôleurs d'authentification

### RegisterController.php

Gère le processus d'inscription:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'dob' => 'required|date|before:-18 years',
            'gender' => 'required|string|in:male,female',
            'terms' => 'required|accepted',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'client',
        ]);

        // Generate token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        Auth::login($user);

        return redirect()->route('home');
    }
}
```

### LoginController.php

Gère le processus de connexion:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Generate token for API access
            $token = $user->createToken('auth_token')->plainTextToken;

            // Check user type for redirect
            if ($user->type === 'admin' || $user->type === 'moderateur') {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->withInput($request->except('password'));
    }

    public function logout(Request $request)
    {
        // Revoke all user's tokens
        if (Auth::check()) {
            Auth::user()->tokens()->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

### ForgotPasswordController.php

Gère les demandes de réinitialisation de mot de passe:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => __($status)])
                    : back()->withErrors(['email' => __($status)]);
    }
}
```

### ResetPasswordController.php

Gère la réinitialisation du mot de passe:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request)
    {
        $token = $request->route()->parameter('token');

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->input('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withErrors(['email' => [__($status)]]);
    }
}
```

## 7. Configuration des Routes

```php
<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Guest routes
Route::middleware('guest')->group(function () {
    // Registration Routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Login Routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Password Reset Routes
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// Auth routes
Route::middleware('auth')->group(function () {
    // Logout Route
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Client profile
    Route::get('/profil', function () {
        return Inertia::render('Profile/Show');
    })->name('profile');
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');
});

// Moderator routes
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->name('moderator.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');
});
```

## 8. Composants Vue pour l'authentification

### Structure des composants

```
resources/
└── js/
    ├── Admin/
    │   └── Pages/
    │       └── Auth/
    │           └── Login.vue
    └── Client/
        ├── Layouts/
        │   └── GuestLayout.vue
        └── Pages/
            └── Auth/
                ├── Login.vue
                ├── Register.vue
                ├── ForgotPassword.vue
                └── ResetPassword.vue
```

### GuestLayout.vue

```vue
<template>
    <div class="text-gray-800">
        <!-- Header -->
        <header class="gradient-bg text-white shadow-lg">
            <div class="container mx-auto px-4 py-4">
                <div class="flex items-center justify-center space-x-2">
                    <i class="fas fa-heart text-2xl"></i>
                    <h1 class="text-2xl font-bold">HeartMatch</h1>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-6 mt-8">
            <div class="container mx-auto px-4">
                <div
                    class="flex flex-col md:flex-row justify-between items-center"
                >
                    <div class="flex items-center space-x-2 mb-4 md:mb-0">
                        <i class="fas fa-heart text-pink-500"></i>
                        <span class="font-medium">HeartMatch</span>
                    </div>
                    <div class="flex space-x-6">
                        <a
                            href="#"
                            class="text-gray-600 hover:text-pink-500 text-sm"
                            >Confidentialité</a
                        >
                        <a
                            href="#"
                            class="text-gray-600 hover:text-pink-500 text-sm"
                            >Conditions</a
                        >
                        <a
                            href="#"
                            class="text-gray-600 hover:text-pink-500 text-sm"
                            >Aide</a
                        >
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, #f9a8d4 0%, #f472b6 100%);
}

.divider {
    display: flex;
    align-items: center;
    text-align: center;
    color: #9ca3af;
}

.divider::before,
.divider::after {
    content: "";
    flex: 1;
    border-bottom: 1px solid #e5e7eb;
}

.divider::before {
    margin-right: 1rem;
}

.divider::after {
    margin-left: 1rem;
}
</style>
```

### Register.vue (exemple simplifié)

```vue
<template>
    <GuestLayout>
        <div
            class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8"
        >
            <!-- Contenu du formulaire d'inscription -->
            <form @submit.prevent="submit">
                <!-- Champs du formulaire -->
                <button
                    type="submit"
                    class="signup-btn w-full bg-pink-500 text-white py-3 px-4 rounded-lg"
                >
                    S'inscrire
                </button>
            </form>
        </div>
    </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useForm } from "@inertiajs/vue3";
import GuestLayout from "@client/Layouts/GuestLayout.vue";
import { route } from "ziggy-js";

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    dob: "",
    gender: "male",
    terms: false,
});

const submit = () => {
    form.post(route("register"));
};
</script>
```

## 9. Utilisation des Tokens Sanctum

### Pour l'authentification API

Sanctum génère automatiquement des tokens pour les utilisateurs lors de leur inscription/connexion. Ces tokens peuvent être utilisés pour authentifier les requêtes API.

#### Exemple de génération de token:

```php
// Dans RegisterController.php ou LoginController.php
$token = $user->createToken('auth_token')->plainTextToken;
```

#### Utilisation du token côté client:

```javascript
// Exemple avec axios
const response = await axios.get("/api/user", {
    headers: {
        Authorization: `Bearer ${token}`,
    },
});
```

## 10. Différences entre les types d'utilisateurs

### Pages d'accueil après connexion:

-   **Admin**: Redirigé vers `/admin/dashboard`
-   **Modérateur**: Redirigé vers `/moderateur/dashboard`
-   **Client**: Redirigé vers `/` (page d'accueil)

### Accès aux fonctionnalités:

-   Les routes admin sont protégées par le middleware `admin`
-   Les routes modérateur sont protégées par le middleware `moderator`
-   Certaines fonctionnalités peuvent être conditionnellement affichées en fonction du type d'utilisateur

## 11. Sécurité et bonnes pratiques

1. **Validation des entrées**: Toutes les entrées utilisateur sont validées côté serveur
2. **Hachage des mots de passe**: Les mots de passe sont hachés avec Bcrypt via `Hash::make()`
3. **Protection CSRF**: Laravel inclut automatiquement la protection CSRF
4. **Régénération de session**: Les sessions sont régénérées lors de la connexion/déconnexion
5. **Vérification d'âge**: Validation que l'utilisateur a au moins 18 ans lors de l'inscription

## 12. Points d'extension

Le système peut être étendu avec:

1. **Vérification d'email**: Implémenter `MustVerifyEmail` pour la vérification des adresses email
2. **Authentification à deux facteurs**: Ajouter 2FA pour plus de sécurité
3. **OAuth**: Ajouter l'authentification via Google, Facebook, etc.
4. **Audit de connexion**: Enregistrer les tentatives de connexion pour analyse

## 13. Dépannage commun

1. **Problème de route**: Assurez-vous que Ziggy est correctement configuré pour les routes nommées
2. **Token invalide**: Vérifiez que Sanctum est correctement configuré et les domaines autorisés
3. **Redirection en boucle**: Vérifiez la logique dans RedirectIfAuthenticated
4. **Problème CORS**: Configurez correctement les en-têtes CORS dans le fichier `cors.php`

## 14. Conclusion

Ce système d'authentification fournit une base solide pour votre application de rencontre. Il combine la puissance de Laravel Sanctum pour la gestion des tokens API avec Inertia.js et Vue 3 pour une expérience utilisateur fluide. Le système de rôles intégré (client, modérateur, admin) permet une gestion fine des permissions et des fonctionnalités.
