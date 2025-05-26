<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestEventController;
use App\Http\Controllers\Client\ProfilePointController;
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

// Stripe webhook route for profile points
Route::post('/stripe/profile-points/webhook', [ProfilePointController::class, 'handleWebhook'])
    ->name('stripe.profile-points.webhook')
    ->withoutMiddleware(['csrf']);
