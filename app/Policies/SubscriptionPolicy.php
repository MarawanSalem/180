<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::SubscriptionsViewAny)
            || $user->can(PermissionName::SubscriptionsView);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        if ($user->can(PermissionName::SubscriptionsViewAny)) {
            return true;
        }

        return $user->can(PermissionName::SubscriptionsView)
            && $this->owns($user, $subscription);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::SubscriptionsCreate);
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        if ($user->can(PermissionName::SubscriptionsViewAny)
            && $user->can(PermissionName::SubscriptionsCancel)) {
            return true;
        }

        return $user->can(PermissionName::SubscriptionsCancel)
            && $this->owns($user, $subscription);
    }

    public function upgrade(User $user, Subscription $subscription): bool
    {
        if ($user->can(PermissionName::SubscriptionsViewAny)
            && $user->can(PermissionName::SubscriptionsUpgrade)) {
            return true;
        }

        return $user->can(PermissionName::SubscriptionsUpgrade)
            && $this->owns($user, $subscription);
    }

    private function owns(User $user, Subscription $subscription): bool
    {
        return (int) $user->id === (int) $subscription->user_id;
    }
}
