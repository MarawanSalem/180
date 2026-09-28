<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Enums\RoleName;

/**
 * Maps each role to its permission set. Super admin is handled via Gate::before.
 */
final class RolePermissionMap
{
    /**
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        $finance = [
            PermissionName::AdminAccess,
            PermissionName::InstructorsViewAny,
            PermissionName::InstructorsView,
            PermissionName::LedgerView,
            PermissionName::SubscriptionsViewAny,
            PermissionName::SubscriptionsView,
            PermissionName::SubscriptionsCreate,
            PermissionName::PeriodsView,
            PermissionName::PeriodsAllocate,
            PermissionName::PayoutsViewAny,
            PermissionName::PayoutsView,
            PermissionName::PayoutsRun,
            PermissionName::PayoutsReconcile,
            PermissionName::PayoutBatchesViewAny,
            PermissionName::PayoutBatchesView,
        ];

        $ops = [
            PermissionName::AdminAccess,
            PermissionName::InstructorsViewAny,
            PermissionName::InstructorsView,
            PermissionName::LedgerView,
            PermissionName::SubscriptionsViewAny,
            PermissionName::SubscriptionsView,
            PermissionName::PeriodsView,
            PermissionName::PeriodsAllocate,
            PermissionName::PayoutsViewAny,
            PermissionName::PayoutsView,
            PermissionName::PayoutBatchesViewAny,
            PermissionName::PayoutBatchesView,
            PermissionName::PayoutsReconcile,
            PermissionName::UsersViewAny,
            PermissionName::UsersView,
            PermissionName::RolesViewAny,
            PermissionName::RolesView,
        ];

        return [
            RoleName::SuperAdmin->value => PermissionName::all(),
            RoleName::FinanceAdmin->value => array_merge($finance, [
                PermissionName::UsersViewAny,
                PermissionName::UsersView,
                PermissionName::RolesViewAny,
                PermissionName::RolesView,
            ]),
            RoleName::Ops->value => $ops,
            RoleName::Instructor->value => [
                PermissionName::InstructorsView,
                PermissionName::LedgerView,
                PermissionName::PayoutsView,
            ],
            RoleName::Student->value => [
                PermissionName::SubscriptionsView,
                PermissionName::SubscriptionsCreate,
                PermissionName::SubscriptionsCancel,
                PermissionName::SubscriptionsUpgrade,
            ],
        ];
    }
}
