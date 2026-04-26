<?php

use App\Domain\Auth\Http\Controllers\LoginUserController;
use App\Domain\Auth\Http\Controllers\RegisterUserController;
use App\Domain\Content\Http\Controllers\ChannelController;
use App\Domain\Content\Http\Controllers\FacebookSocialController;
use App\Domain\Content\Http\Controllers\PlatformController;
use App\Domain\Content\Http\Controllers\PostController;
use App\Domain\Content\Http\Controllers\PostMediaController;
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

    Route::get('/social/facebook/callback', [FacebookSocialController::class, 'callback'])->name('social.facebook.callback');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('platforms', [PlatformController::class, 'index'])->name('platforms.index');

        Route::prefix('channels')->name('channels.')->group(function () {
            Route::get('/', [ChannelController::class, 'index'])->name('index');
            Route::post('/', [ChannelController::class, 'connect'])->name('connect');
            Route::delete('/{channel}', [ChannelController::class, 'disconnect'])->name('disconnect');
        });

        Route::prefix('social/facebook')->name('social.facebook.')->group(function () {
            Route::get('/connect', [FacebookSocialController::class, 'connect'])->name('connect');
            Route::post('/channels', [FacebookSocialController::class, 'connectChannels'])->name('channels.connect');
        });

        Route::prefix('posts/media')->name('media.')->group(function () {
            Route::post('/upload', [PostMediaController::class, 'upload'])->name('upload');
            Route::post('/attach', [PostMediaController::class, 'attach'])->name('attach');
            Route::delete('/{media}', [PostMediaController::class, 'delete'])->name('delete');
        });

        Route::prefix('posts')->name('posts.')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::post('/', [PostController::class, 'store'])->name('store');
            Route::get('/{post}', [PostController::class, 'show'])->name('show');
            Route::patch('/{post}', [PostController::class, 'update'])->name('update');
            Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
        });
    });
});
