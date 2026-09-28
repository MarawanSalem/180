<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Repositories\Eloquent\EloquentInstructorLedgerEntryRepository;
use App\Repositories\Eloquent\EloquentPayoutBatchRepository;
use App\Repositories\Eloquent\EloquentPayoutRepository;
use App\Repositories\Eloquent\EloquentSubscriptionPeriodRepository;
use App\Repositories\Eloquent\EloquentSubscriptionRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Interfaces\InstructorLedgerEntryRepositoryInterface;
use App\Repositories\Interfaces\PayoutBatchRepositoryInterface;
use App\Repositories\Interfaces\PayoutRepositoryInterface;
use App\Repositories\Interfaces\SubscriptionPeriodRepositoryInterface;
use App\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Payment\MockPaymentProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, EloquentSubscriptionRepository::class);
        $this->app->bind(SubscriptionPeriodRepositoryInterface::class, EloquentSubscriptionPeriodRepository::class);
        $this->app->bind(PayoutRepositoryInterface::class, EloquentPayoutRepository::class);
        $this->app->bind(PayoutBatchRepositoryInterface::class, EloquentPayoutBatchRepository::class);
        $this->app->bind(InstructorLedgerEntryRepositoryInterface::class, EloquentInstructorLedgerEntryRepository::class);

        $this->app->singleton(MockPaymentProvider::class);
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }
}
