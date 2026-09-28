<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PayoutBatch;
use App\Models\User;
use App\Support\Authorization\PermissionName;

class PayoutBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PayoutBatchesViewAny);
    }

    public function view(User $user, PayoutBatch $payoutBatch): bool
    {
        return $user->can(PermissionName::PayoutBatchesView)
            || $user->can(PermissionName::PayoutBatchesViewAny);
    }
}
