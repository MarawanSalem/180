<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class SubscriptionPeriodPolicy
{
    public function view(User $user, SubscriptionPeriod $period): bool
    {
        return $user->can(PermissionName::PeriodsView);
    }

    public function allocate(User $user, SubscriptionPeriod $period): bool
    {
        return $user->can(PermissionName::PeriodsAllocate);
    }
}
