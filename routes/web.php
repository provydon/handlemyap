<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Public invoice intake — vendors submit to /u/{handle} with no login.
Route::get('/u/{handle}', [IntakeController::class, 'show'])->name('intake');
Route::post('/u/{handle}', [IntakeController::class, 'store'])->middleware('throttle:15,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/app', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/app/data', [DashboardController::class, 'data']);
    Route::post('/app/invoices', [DashboardController::class, 'store']);
    Route::post('/app/invoices/{invoice}/approve', [DashboardController::class, 'approve']);
    Route::post('/app/invoices/{invoice}/hold', [DashboardController::class, 'hold']);
    Route::post('/app/invoices/{invoice}/reject', [DashboardController::class, 'reject']);

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/rules', [SettingsController::class, 'updateRule']);
    Route::post('/settings/handle', [SettingsController::class, 'updateHandle']);
    Route::post('/settings/tokens', [SettingsController::class, 'createToken']);
    Route::delete('/settings/tokens/{id}', [SettingsController::class, 'deleteToken']);
});
