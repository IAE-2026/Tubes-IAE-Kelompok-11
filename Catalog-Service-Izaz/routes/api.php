<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\AddonController;
use App\Http\Controllers\Api\SsoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are protected by the 'sso.auth' middleware which verifies
| JWT Bearer tokens from the central SSO server (Cloud Dosen).
|
*/

// ── SSO-protected routes (JWT Bearer token required) ──
Route::prefix('v1')->middleware('sso.auth')->group(function () {

    // Rooms — Catalog CRUD + full integration (SOAP + RabbitMQ)
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);
    Route::post('/rooms', [RoomController::class, 'store']);

    // Addons
    Route::get('/addons', [AddonController::class, 'index']);

    // SSO user info
    Route::get('/sso/me', [SsoController::class, 'me']);
    Route::post('/sso/verify', [SsoController::class, 'verify']);
});

// ── Internal routes (X-INTERNAL-KEY required for M2M communication) ──
Route::prefix('internal')->middleware('internal.key')->group(function () {
    // Rooms — Catalog CRUD
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);

    // Addons — Daftar Addons
    Route::get('/addons', [AddonController::class, 'index']);
});
