<?php

declare(strict_types=1);

namespace App\Http\Routes\Api\V1;

use App\Http\Controllers\Api\V1\Subscription\PeriodAllocationController;
use App\Http\Controllers\Api\V1\Subscription\SubscriptionController;
use Illuminate\Support\Facades\Route;

final class SubscriptionRoutes
{
    public static function register(): void
    {
        Route::middleware('auth:sanctum')->group(function (): void {
            // Subscription CRUD + actions.
            Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
            Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription')->name('subscriptions.show');
            Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->whereNumber('subscription')->name('subscriptions.cancel');
            Route::post('subscriptions/{subscription}/upgrade', [SubscriptionController::class, 'upgrade'])->whereNumber('subscription')->name('subscriptions.upgrade');

            // Revenue allocation for a specific period.
            Route::post('subscription-periods/{period}/allocate', [PeriodAllocationController::class, 'allocate'])->whereNumber('period')->name('subscription-periods.allocate');
            Route::get('subscription-periods/{period}', [PeriodAllocationController::class, 'show'])->whereNumber('period')->name('subscription-periods.show');
        });
    }
}
