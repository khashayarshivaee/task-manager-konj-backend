<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('profile')
    ->middleware([
        'auth:sanctum',
        EnsureUserIsActive::class,
    ])
    ->group(function (): void {
        Route::post('/', [ProfileController::class, 'update']);

        Route::put('/password', [
            ProfileController::class,
            'updatePassword',
        ]);
    });
