<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class PayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PayoutsViewAny);
    }

    public function view(User $user, Payout $payout): bool
    {
        if ($user->can(PermissionName::PayoutsViewAny)) {
            return true;
        }

        return $user->can(PermissionName::PayoutsView)
            && (int) $user->id === (int) $payout->instructor_id;
    }

    public function run(User $user): bool
    {
        return $user->can(PermissionName::PayoutsRun);
    }

    public function reconcile(User $user): bool
    {
        return $user->can(PermissionName::PayoutsReconcile);
    }
}
