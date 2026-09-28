<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\V1;

use App\Http\Controllers\Api\V1\Ledger\LedgerEntryController;
use Illuminate\Support\Facades\Route;

final class LedgerRoutes
{
    public static function register(): void
    {
        Route::middleware('auth:sanctum')->group(function (): void {
            // Instructor ledger entries.
            Route::get('instructors/{instructor}/ledger', [LedgerEntryController::class, 'index'])->whereNumber('instructor')->name('instructors.ledger.index');

            // Instructor current balance (computed from the append-only ledger).
            Route::get('instructors/{instructor}/balance', [LedgerEntryController::class, 'balance'])->whereNumber('instructor')->name('instructors.balance');
        });
    }
}
