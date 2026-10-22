<?php

use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\RuleController;
use App\Http\Controllers\Api\V1\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| handlemyap REST API (v1) — Sanctum bearer token auth.
|--------------------------------------------------------------------------
| This is how other apps (e.g. NotaryDash) submit payables and drive payouts.
| Create a token at /settings/api in the dashboard.
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('me', fn (\Illuminate\Http\Request $r) => $r->user()->only('id', 'name', 'email'));

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve']);
    Route::post('invoices/{invoice}/hold', [InvoiceController::class, 'hold']);
    Route::post('invoices/{invoice}/reject', [InvoiceController::class, 'reject']);

    Route::get('vendors', [VendorController::class, 'index']);
    Route::post('vendors', [VendorController::class, 'store']);

    Route::get('payouts', [PayoutController::class, 'index']);
    Route::get('payouts/{payout}', [PayoutController::class, 'show']);

    Route::get('rules', [RuleController::class, 'show']);
    Route::put('rules', [RuleController::class, 'update']);
});
