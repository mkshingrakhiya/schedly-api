<?php

use App\Domain\Auth\Http\Controllers\LoginUserController;
use App\Domain\Auth\Http\Controllers\RegisterUserController;
use App\Domain\Content\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Quick ping/test route
Route::get('/ping', fn () => ['message' => 'pong']);

Route::prefix('v1')->name('v1.')->group(function () {
    // Auth routes
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', RegisterUserController::class)
            ->middleware('throttle:10,1')
            ->name('register');

        Route::post('/login', LoginUserController::class)
            ->middleware('throttle:5,1')
            ->name('login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostController::class)
            ->parameters(['posts' => 'post']);
    });
});
