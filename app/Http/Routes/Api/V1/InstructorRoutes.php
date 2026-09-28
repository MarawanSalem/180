<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\V1;

use App\Http\Controllers\Api\V1\Instructor\InstructorController;
use App\Http\Controllers\Api\V1\Payout\PayoutController;
use Illuminate\Support\Facades\Route;

final class InstructorRoutes
{
    public static function register(): void
    {
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('instructors', [InstructorController::class, 'index'])->name('instructors.index');
            Route::get('instructors/{instructor}', [InstructorController::class, 'show'])->whereNumber('instructor')->name('instructors.show');
            Route::get('instructors/{instructor}/payouts', [PayoutController::class, 'indexForInstructor'])->whereNumber('instructor')->name('instructors.payouts.index');
        });
    }
}
