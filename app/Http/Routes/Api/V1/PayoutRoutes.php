<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\V1;

use App\Http\Controllers\Api\V1\Payout\PayoutBatchController;
use App\Http\Controllers\Api\V1\Payout\PayoutController;
use Illuminate\Support\Facades\Route;

final class PayoutRoutes
{
    public static function register(): void
    {
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('payouts/run', [PayoutController::class, 'run'])->name('payouts.run');
            Route::post('payouts/reconcile', [PayoutController::class, 'reconcile'])->name('payouts.reconcile');

            Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
            Route::get('payouts/{payout}', [PayoutController::class, 'show'])->whereNumber('payout')->name('payouts.show');

            Route::get('payout-batches', [PayoutBatchController::class, 'index'])->name('payout-batches.index');
            Route::get('payout-batches/{payoutBatch}', [PayoutBatchController::class, 'show'])->whereNumber('payoutBatch')->name('payout-batches.show');
        });
    }
}
