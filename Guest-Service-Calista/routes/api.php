<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\GuestController;
use App\Http\Middleware\ApiKeyMiddleware;

// Tambahkan middleware 
Route::prefix('v1')->middleware('sso.jwt')->group(function () {
    
    // GET /{guestId}
    Route::get('/{guestId}', [GuestController::class, 'show']);
    
    // POST /profile
    Route::post('/profile', [GuestController::class, 'storeProfile']);
    
    // POST /validate-ktp
    Route::post('/validate-ktp', [GuestController::class, 'validateKtp']);

});

// Internal API routes (using internal.key middleware instead of SSO JWT)
Route::prefix('internal')->middleware('internal.key')->group(function () {
    
    // GET /internal/{guestId}
    Route::get('/{guestId}', [GuestController::class, 'show']);
    
    // POST /internal/profile
    Route::post('/profile', [GuestController::class, 'storeProfile']);
    
    // POST /internal/validate-ktp
    Route::post('/validate-ktp', [GuestController::class, 'validateKtp']);

});