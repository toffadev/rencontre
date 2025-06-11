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
use App\Http\Controllers\Admin\ModeratorPerformanceController;
use App\Http\Controllers\Admin\ProfilePerformanceController;
use App\Models\User;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Route d'authentification pour les broadcasts
Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
    return Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware(['auth', 'broadcast_auth'])->name('broadcasting.auth');

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

// Ajoutez cette route dans votre web.php :
Route::get('/auth/check', function () {
    return response()->json([
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'csrf_token' => csrf_token()
    ]);
})->middleware('web');
// Route principale qui redirige en fonction de l'authentification
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        // Redirection selon le type d'utilisateur
        if ($user->type === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->type === 'moderateur') {
            return redirect()->route('moderator.chat');
        }

        // Si c'est un client, afficher la page d'accueil client
        return app()->make(HomeController::class)->index();
    } else {
        // Si l'utilisateur n'est pas connecté, rediriger vers login
        return redirect()->route('login');
    }
})->name('home');

// Routes qui nécessitent une authentification client uniquement
Route::middleware(['auth', 'client_only'])->group(function () {
    // Profile setup routes
    Route::get('/profile-setup', [App\Http\Controllers\Client\ProfileSetupController::class, 'show'])
        ->name('profile.setup');
    Route::post('/profile-setup', [App\Http\Controllers\Client\ProfileSetupController::class, 'store'])
        ->name('profile.setup.store');

    // Page d'accueil client explicite (URL: /home)
    Route::get('/', [HomeController::class, 'index'])->name('client.home');

    // Client area routes
    Route::get('/profil', function () {
        $user = Auth::user();
        $profile = $user->clientProfile;

        return Inertia::render('Profil/Show', [
            'profileData' => [
                'photo_url' => $profile->profile_photo_url,
                'name' => $user->name,
                'city' => $profile->city,
                'country' => $profile->country,
                'age' => $profile->birth_date ? $profile->birth_date->age : null,
                'bio' => $profile->bio,
                'registration_date' => $user->created_at->format('d/m/Y'),
                'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Aujourd\'hui',
                'relationship_status' => $profile->relationship_status,
            ]
        ]);
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
    Route::post('/messages/mark-as-read', [App\Http\Controllers\Client\MessageController::class, 'markAsRead'])->name('client.messages.mark-as-read');
    Route::get('/active-conversations', [App\Http\Controllers\Client\MessageController::class, 'getActiveConversations'])->name('client.active-conversations');

    // Nouvelle route pour les pièces jointes
    Route::post('/messages/upload-attachment', [App\Http\Controllers\Client\MessageController::class, 'uploadAttachment'])->name('client.messages.upload-attachment');

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
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [App\Http\Controllers\Admin\DashboardController::class, 'getStats'])->name('dashboard.stats');

    // Conversation Viewer routes
    Route::prefix('conversations')->name('conversations.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ConversationController::class, 'index'])->name('index');
        Route::get('/clients', [App\Http\Controllers\Admin\ConversationController::class, 'getClients'])->name('clients');
        Route::get('/clients/{clientId}/profiles', [App\Http\Controllers\Admin\ConversationController::class, 'getClientProfiles'])->name('client.profiles');
        Route::get('/conversation/{clientId}/{profileId}', [App\Http\Controllers\Admin\ConversationController::class, 'getConversation'])->name('get');
    });

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

    // Global message management routes
    Route::prefix('messages')->name('messages.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\GlobalMessageController::class, 'index'])->name('index');
        Route::get('/list', [App\Http\Controllers\Admin\GlobalMessageController::class, 'getMessages'])->name('list');
        Route::get('/filters', [App\Http\Controllers\Admin\GlobalMessageController::class, 'getFilters'])->name('filters');
        Route::post('/mark-as-read', [App\Http\Controllers\Admin\GlobalMessageController::class, 'markAsRead'])->name('mark-as-read');
        Route::post('/mark-as-unread', [App\Http\Controllers\Admin\GlobalMessageController::class, 'markAsUnread'])->name('mark-as-unread');
        Route::delete('/', [App\Http\Controllers\Admin\GlobalMessageController::class, 'destroy'])->name('destroy');
    });

    // Routes pour la gestion des clients
    Route::prefix('clients')->name('clients.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ClientManagementController::class, 'index'])->name('index');
        Route::get('/{client}', [App\Http\Controllers\Admin\ClientManagementController::class, 'show'])->name('show');
        Route::post('/{client}/points', [App\Http\Controllers\Admin\ClientManagementController::class, 'adjustPoints'])->name('adjust-points');
    });

    // Routes API pour les performances des modérateurs
    Route::prefix('api')->group(function () {
        Route::get('/moderators', [App\Http\Controllers\Admin\ModeratorController::class, 'index']);
        Route::get('/profiles', [App\Http\Controllers\Admin\AdminProfileApiController::class, 'index']);
    });

    // Routes pour les performances des modérateurs
    Route::prefix('moderator-performance')->group(function () {
        Route::get('/', [ModeratorPerformanceController::class, 'index'])->name('moderator-performance.index');
        Route::get('/data', [ModeratorPerformanceController::class, 'getData'])->name('moderator-performance.data');
        Route::get('/export', [ModeratorPerformanceController::class, 'export'])->name('moderator-performance.export');
        Route::get('/moderators', [App\Http\Controllers\Admin\ModeratorController::class, 'index'])->name('moderator-performance.moderators');
        Route::get('/profiles', [App\Http\Controllers\Admin\AdminProfileApiController::class, 'index'])->name('moderator-performance.profiles');

        // Nouvelles routes pour les détails des modérateurs
        Route::get('/moderator/{id}', [App\Http\Controllers\Admin\ModeratorDetailsController::class, 'show'])->name('moderator-performance.moderator.show');
        Route::get('/moderator/{id}/details', [App\Http\Controllers\Admin\ModeratorDetailsController::class, 'getDetails'])->name('moderator-performance.moderator.details');
        Route::get('/moderator/{id}/messages', [App\Http\Controllers\Admin\ModeratorDetailsController::class, 'getMessages'])->name('moderator-performance.moderator.messages');
        Route::get('/moderator/{id}/payments', [App\Http\Controllers\Admin\ModeratorDetailsController::class, 'getPayments'])->name('moderator-performance.moderator.payments');

        // Route pour mettre à jour le statut de paiement d'un modérateur
        Route::post('/moderator/{id}/payment-status', [App\Http\Controllers\Admin\ModeratorDetailsController::class, 'updatePaymentStatus'])->name('moderator-performance.moderator.payment-status');
    });

    // Routes pour la gestion des messages des modérateurs
    Route::prefix('moderators')->name('moderators.')->group(function () {
        Route::get('/messages', function () {
            return Inertia::render('ModeratorMessagesList');
        })->name('messages.list');
        Route::get('/{moderator_id}/messages', [App\Http\Controllers\Admin\ModeratorMessageController::class, 'index'])->name('messages.index');
        Route::get('/{moderator_id}/messages/data', [App\Http\Controllers\Admin\ModeratorMessageController::class, 'getMessages'])->name('messages.data');
        Route::get('/conversation', [App\Http\Controllers\Admin\ModeratorMessageController::class, 'getConversation'])->name('messages.conversation');
    });

    // Routes pour les transactions financières
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\FinancialTransactionController::class, 'index'])->name('index');
        Route::get('/stats', [App\Http\Controllers\Admin\FinancialTransactionController::class, 'getStats'])->name('stats');
        Route::get('/export', [App\Http\Controllers\Admin\FinancialTransactionController::class, 'export'])->name('export');
    });

    // Routes pour l'attribution des points aux modérateurs
    Route::prefix('moderator-points')->name('moderator-points.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ModeratorPointsController::class, 'index'])->name('index');
        Route::get('/{moderator}/stats', [App\Http\Controllers\Admin\ModeratorPointsController::class, 'getModeratorStats'])->name('stats');
        Route::post('/bonus', [App\Http\Controllers\Admin\ModeratorPointsController::class, 'addBonus'])->name('bonus.add');
        Route::get('/export', [App\Http\Controllers\Admin\ModeratorPointsController::class, 'export'])->name('export');
    });

    // Routes pour les performances des profils
    Route::prefix('profile-performance')->name('profile-performance.')->group(function () {
        // Vue d'ensemble des performances
        Route::get('/', [ProfilePerformanceController::class, 'index'])->name('index');
        Route::get('/data', [ProfilePerformanceController::class, 'getData'])->name('data');

        // Détails d'un profil spécifique
        Route::get('/{profile}/messages', [ProfilePerformanceController::class, 'getMessages'])->name('messages');
        Route::get('/{profile}/charts', [ProfilePerformanceController::class, 'getCharts'])->name('charts');
        Route::get('/{profile}/top-clients', [ProfilePerformanceController::class, 'getTopClients'])->name('top-clients');

        // Actions sur les profils
        Route::post('/{profile}/assign-moderator', [ProfilePerformanceController::class, 'assignModerator'])->name('assign-moderator');

        // Export des données
        Route::get('/export', [ProfilePerformanceController::class, 'export'])->name('export');
    });

    // Routes pour les notifications
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/mark-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Routes pour la gestion des signalements
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ProfileReportController::class, 'index'])->name('index');
        Route::get('/list', [App\Http\Controllers\Admin\ProfileReportController::class, 'getReports'])->name('list');
        Route::post('/{id}/accept', [App\Http\Controllers\Admin\ProfileReportController::class, 'accept'])->name('accept');
        Route::post('/{id}/dismiss', [App\Http\Controllers\Admin\ProfileReportController::class, 'dismiss'])->name('dismiss');
        Route::get('/{id}', [App\Http\Controllers\Admin\ProfileReportController::class, 'show'])->name('show');
    });
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
    Route::post('/send-message', [App\Http\Controllers\Moderator\ModeratorController::class, 'sendMessage'])
        ->name('send-message')
        ->middleware(['web', 'auth', 'moderator']);
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
    Route::get('/profile/monthly-earnings', [App\Http\Controllers\Moderator\ModeratorProfileController::class, 'getMonthlyEarnings'])->name('profile.monthly-earnings');

    // Moderator management routes will go here
});

Route::get('/check-active-discussion/{profileId}', [ProfileDiscussionController::class, 'checkActiveDiscussion'])
    ->name('profile.check-discussion');
