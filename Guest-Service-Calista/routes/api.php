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