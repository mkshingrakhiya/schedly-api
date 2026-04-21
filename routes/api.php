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

    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');

        Route::get('posts/{postUuid}', [PostController::class, 'show'])
            ->whereUuid('postUuid')
            ->name('posts.show');

        Route::patch('posts/{postUuid}', [PostController::class, 'update'])
            ->whereUuid('postUuid')
            ->name('posts.update');

        Route::delete('posts/{postUuid}', [PostController::class, 'destroy'])
            ->whereUuid('postUuid')
            ->name('posts.destroy');
    });
});
