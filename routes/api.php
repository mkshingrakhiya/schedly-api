<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;

Route::get('/ping', fn () => ['message' => 'pong']);

Route::prefix('v1')->group(function () {
    Route::post('/register', RegisteredUserController::class)
        ->middleware('throttle:10,1');
});
