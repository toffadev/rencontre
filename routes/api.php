<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestEventController;
use App\Http\Controllers\Client\ProfilePointController;
use Illuminate\Support\Facades\Auth;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route pour tester l'envoi d'événements
Route::post('/test-event', [TestEventController::class, 'sendTestEvent']);

// Dans routes/api.php
Route::get('/auth-test', function (Request $request) {
    return [
        'authenticated' => Auth::check(),
        'user' => Auth::user(),
        'session_id' => session()->getId(),
        'cookies' => $request->cookies->all(),
    ];
})->middleware('web');

// Stripe webhook route for profile points
Route::post('/stripe/profile-points/webhook', [ProfilePointController::class, 'handleWebhook'])
    ->name('stripe.profile-points.webhook')
    ->withoutMiddleware(['csrf']);

// Route de diagnostic pour les modérateurs
Route::middleware(['auth:sanctum', 'moderator'])->get('/moderateur/diagnostic', [App\Http\Controllers\Moderator\ModeratorController::class, 'diagnosticStatus']);
