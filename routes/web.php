<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Events\TestEvent;
use App\Http\Controllers\Client\HomeController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Client\ProfileReportController;
use App\Http\Controllers\Client\ProfileDiscussionController;
use App\Models\Profile;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/test-redis', function () {
    try {
        Redis::set('hello', 'Redis fonctionne !');
        $value = Redis::get('hello');
        return "Test Redis réussi : " . $value;
    } catch (\Exception $e) {
        Log::error('Erreur Redis : ' . $e->getMessage());
        return "Erreur Redis : " . $e->getMessage();
    }
});

// Route principale qui redirige en fonction de l'authentification
Route::get('/', function () {
    if (Auth::check()) {
        // Si l'utilisateur est connecté, afficher la page d'accueil client
        // via le HomeController (qui vérifie déjà les permissions via middleware)
        return app()->make(HomeController::class)->index();
    } else {
        // Si l'utilisateur n'est pas connecté, rediriger vers login
        return redirect()->route('login');
    }
})->name('home');

// Routes qui nécessitent une authentification client ou admin
Route::middleware(['client_or_admin'])->group(function () {
    // Page d'accueil client explicite (URL: /home)
    Route::get('/home', [HomeController::class, 'index'])->name('client.home');

    // Client area routes
    Route::get('/profil', function () {
        return Inertia::render('Profil/Show');
    })->name('profile');

    // Route pour la page des points d'un profil
    Route::get('/profile/{profile}/points', function (Profile $profile) {
        return Inertia::render('Profile/Points', [
            'profile' => $profile,
            'stripeKey' => config('services.stripe.key')
        ]);
    })->name('client.profile.points');

    // Routes pour les messages du client
    Route::get('/messages', [App\Http\Controllers\Client\MessageController::class, 'getMessages'])->name('client.messages');
    Route::post('/send-message', [App\Http\Controllers\Client\MessageController::class, 'sendMessage'])->name('client.send-message');

    // Routes pour la gestion des points
    Route::get('/points/data', [App\Http\Controllers\Client\PointController::class, 'getPointsData'])->name('client.points.data');
    Route::post('/points/checkout', [App\Http\Controllers\Client\PointController::class, 'createCheckoutSession'])->name('client.points.checkout');
    Route::get('/points/success', [App\Http\Controllers\Client\PointController::class, 'success'])->name('client.points.success');

    // Routes pour les points des profils
    Route::prefix('profile-points')->name('profile.points.')->group(function () {
        Route::post('/checkout', [App\Http\Controllers\Client\ProfilePointController::class, 'createCheckoutSession'])->name('checkout');
        Route::get('/success', [App\Http\Controllers\Client\ProfilePointController::class, 'success'])->name('success');
        Route::get('/transactions/profile/{profile}', [App\Http\Controllers\Client\ProfilePointController::class, 'getProfileTransactionHistory'])->name('transactions.profile');
        Route::get('/transactions/client', [App\Http\Controllers\Client\ProfilePointController::class, 'getClientTransactionHistory'])->name('transactions.client');
    });
});

// Routes pour les points
Route::middleware(['auth'])->group(function () {
    Route::get('/points/data', [App\Http\Controllers\Client\PointController::class, 'getPointsData'])->name('points.data');
    Route::post('/points/checkout', [App\Http\Controllers\Client\PointController::class, 'createCheckoutSession'])->name('points.checkout');
    Route::get('/points/success', [App\Http\Controllers\Client\PointController::class, 'success'])->name('client.points.success');
    Route::post('/stripe/webhook', [App\Http\Controllers\Client\PointController::class, 'handleWebhook'])->name('stripe.webhook')->withoutMiddleware(['csrf']);

    // Routes pour les signalements de profils
    Route::post('/profile-reports', [ProfileReportController::class, 'store'])->name('profile-reports.store');
    Route::get('/blocked-profiles', [ProfileReportController::class, 'getBlockedProfiles'])->name('profile-reports.blocked');
});

// Guest routes
Route::middleware('guest')->group(function () {
    // Registration Routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    // Login Routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    // Admin Login Route
    Route::get('/admin/login', [AdminLoginController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'login'])->name('admin.login.submit');

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
});

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Profile management routes
    Route::get('/profiles', [AdminProfileController::class, 'index'])->name('profiles.index');
    Route::post('/profiles', [AdminProfileController::class, 'store'])->name('profiles.store');
    Route::put('/profiles/{profile}', [AdminProfileController::class, 'update'])->name('profiles.update');
    Route::delete('/profiles/{profile}', [AdminProfileController::class, 'destroy'])->name('profiles.destroy');
    Route::put('/profiles/{profile}/main-photo', [AdminProfileController::class, 'setMainPhoto'])->name('profiles.main-photo');
    Route::delete('/profile-photos', [AdminProfileController::class, 'deletePhoto'])->name('profile-photos.destroy');

    // User management routes
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Admin management routes will go here
});

// Moderator routes
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->name('moderator.')->group(function () {
    /* Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard'); */

    // Page principale des modérateurs
    Route::get('/chat', [App\Http\Controllers\Moderator\ModeratorController::class, 'index'])->name('chat');

    // API pour les modérateurs
    Route::get('/clients', [App\Http\Controllers\Moderator\ModeratorController::class, 'getClients'])->name('clients');
    Route::get('/available-clients', [App\Http\Controllers\Moderator\ModeratorController::class, 'getAvailableClients'])->name('available-clients');
    Route::post('/start-conversation', [App\Http\Controllers\Moderator\ModeratorController::class, 'startConversation'])->name('start-conversation');
    Route::get('/profile', [App\Http\Controllers\Moderator\ModeratorController::class, 'getAssignedProfile'])->name('profile');
    Route::get('/messages', [App\Http\Controllers\Moderator\ModeratorController::class, 'getMessages'])->name('messages');
    Route::post('/send-message', [App\Http\Controllers\Moderator\ModeratorController::class, 'sendMessage'])->name('send-message');
    Route::post('/set-primary-profile', [App\Http\Controllers\Moderator\ModeratorController::class, 'setPrimaryProfile'])->name('set-primary-profile');

    // Routes pour les informations client
    Route::get('/clients/{client}/info', [App\Http\Controllers\Moderator\ClientInfoController::class, 'getClientInfo'])->name('client.info');
    Route::post('/clients/{client}/basic-info', [App\Http\Controllers\Moderator\ClientInfoController::class, 'updateBasicInfo'])->name('client.basic-info.update');
    Route::post('/clients/{client}/custom-info', [App\Http\Controllers\Moderator\ClientInfoController::class, 'addCustomInfo'])->name('client.custom-info.add');
    Route::delete('/custom-info/{customInfo}', [App\Http\Controllers\Moderator\ClientInfoController::class, 'deleteCustomInfo'])->name('client.custom-info.delete');

    // Nouvelles routes pour le profil modérateur
    Route::get('/profile-stats', [App\Http\Controllers\Moderator\ModeratorProfileController::class, 'index'])->name('profile.stats');
    Route::get('/profile/statistics', [App\Http\Controllers\Moderator\ModeratorProfileController::class, 'getStatistics'])->name('profile.statistics');
    Route::get('/profile/messages', [App\Http\Controllers\Moderator\ModeratorProfileController::class, 'getMessageHistory'])->name('profile.messages');
    Route::get('/profile/points', [App\Http\Controllers\Moderator\ModeratorProfileController::class, 'getPointsReceived'])->name('profile.points');

    // Moderator management routes will go here
});

Route::get('/check-active-discussion/{profileId}', [ProfileDiscussionController::class, 'checkActiveDiscussion'])
    ->name('profile.check-discussion');
