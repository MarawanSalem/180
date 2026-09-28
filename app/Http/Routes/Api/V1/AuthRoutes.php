<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\V1;

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

final class AuthRoutes
{
    public static function register(): void
    {
        // Public routes — no auth required.
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

        // Protected routes — Sanctum bearer token required.
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        });
    }
}
