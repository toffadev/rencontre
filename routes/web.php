<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Events\TestEvent;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

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

    // Client area routes
    Route::get('/profil', function () {
        return Inertia::render('Profile/Show');
    })->name('profile');
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

    // Admin management routes will go here
});

// Moderator routes
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->name('moderator.')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');

    // Moderator management routes will go here
});
