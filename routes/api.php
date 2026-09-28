<?php

declare(strict_types=1);

use App\Http\Routes\Api\V1\AuthRoutes;
use App\Http\Routes\Api\V1\InstructorRoutes;
use App\Http\Routes\Api\V1\LedgerRoutes;
use App\Http\Routes\Api\V1\PayoutRoutes;
use App\Http\Routes\Api\V1\SubscriptionRoutes;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    AuthRoutes::register();
    InstructorRoutes::register();
    SubscriptionRoutes::register();
    PayoutRoutes::register();
    LedgerRoutes::register();

    
});
