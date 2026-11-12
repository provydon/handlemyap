<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Developer docs for the API + MCP server (public).
Route::view('/docs', 'docs')->name('docs');

// MCP server — JSON-RPC 2.0 over HTTP, Sanctum bearer auth (see McpController).
Route::post('/mcp', [McpController::class, 'handle'])->name('mcp');
Route::get('/mcp', [McpController::class, 'info']);

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
    Route::get('/app/summary', [DashboardController::class, 'summary']);
    Route::get('/app/queue', [DashboardController::class, 'queue']);
    Route::post('/app/invoices', [DashboardController::class, 'store']);
    Route::get('/app/invoices/{invoice}', [DashboardController::class, 'show'])->name('invoice.show');
    Route::get('/app/invoices/{invoice}/file', [DashboardController::class, 'file'])->name('invoice.file');
    Route::post('/app/invoices/{invoice}/hold', [DashboardController::class, 'hold']);
    Route::post('/app/invoices/{invoice}/reject', [DashboardController::class, 'reject']);
    // Remove/void the recorded payment (does not refund via PayPal — see controller).
    Route::delete('/app/invoices/{invoice}/payment', [DashboardController::class, 'removePayment'])->name('invoice.payment.remove');

    // Pay with PayPal (Checkout / Orders v2) — start → approve at PayPal → return/capture.
    Route::post('/app/invoices/{invoice}/pay/paypal', [InvoicePaymentController::class, 'start'])->name('invoice.pay.start');
    Route::post('/app/invoices/{invoice}/pay/paypal/order', [InvoicePaymentController::class, 'order'])->name('invoice.pay.order');
    Route::post('/app/invoices/{invoice}/pay/paypal/capture', [InvoicePaymentController::class, 'capture'])->name('invoice.pay.capture');
    Route::get('/app/invoices/{invoice}/pay/paypal/return', [InvoicePaymentController::class, 'return'])->name('invoice.pay.return');
    Route::get('/app/invoices/{invoice}/pay/paypal/cancel', [InvoicePaymentController::class, 'cancel'])->name('invoice.pay.cancel');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/handle', [SettingsController::class, 'updateHandle']);
    Route::post('/settings/currency', [SettingsController::class, 'updateCurrency']);
    Route::get('/app/vendors', [VendorController::class, 'index'])->name('vendors');
    Route::post('/app/vendors/{vendor}/bank', [VendorController::class, 'storeBank']);

    Route::post('/settings/tokens', [SettingsController::class, 'createToken']);
    Route::delete('/settings/tokens/{id}', [SettingsController::class, 'deleteToken']);
});
