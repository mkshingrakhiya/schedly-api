<?php

use Illuminate\Support\Facades\Route;
use App\Domain\Auth\Http\Controllers\RegisterUserController;

// Quick ping/test route
Route::get('/ping', fn () => ['message' => 'pong']);

Route::prefix('v1')->name('v1.')->group(function () {
    // Auth routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', RegisterUserController::class)
            ->middleware('throttle:10,1')
            ->name('register');
    });
});
