<?php

declare(strict_types=1);

namespace App\Support\Authorization;

/**
 * Canonical Spatie permission names (domain.action).
 * Seed via RolesAndPermissionsSeeder; every hasPermissionTo() must use these constants.
 */
final class PermissionName
{
    public const AdminAccess = 'admin.access';

    public const InstructorsViewAny = 'instructors.view_any';

    public const InstructorsView = 'instructors.view';

    public const LedgerView = 'ledger.view';

    public const SubscriptionsViewAny = 'subscriptions.view_any';

    public const SubscriptionsView = 'subscriptions.view';

    public const SubscriptionsCreate = 'subscriptions.create';

    public const SubscriptionsCancel = 'subscriptions.cancel';

    public const SubscriptionsUpgrade = 'subscriptions.upgrade';

    public const PeriodsView = 'periods.view';

    public const PeriodsAllocate = 'periods.allocate';

    public const PayoutsViewAny = 'payouts.view_any';

    public const PayoutsView = 'payouts.view';

    public const PayoutsRun = 'payouts.run';

    public const PayoutsReconcile = 'payouts.reconcile';

    public const PayoutBatchesViewAny = 'payout_batches.view_any';

    public const PayoutBatchesView = 'payout_batches.view';

    public const UsersViewAny = 'users.view_any';

    public const UsersView = 'users.view';

    public const UsersCreate = 'users.create';

    public const UsersUpdate = 'users.update';

    public const UsersDelete = 'users.delete';

    public const RolesViewAny = 'roles.view_any';

    public const RolesView = 'roles.view';

    public const RolesUpdate = 'roles.update';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::AdminAccess,
            self::InstructorsViewAny,
            self::InstructorsView,
            self::LedgerView,
            self::SubscriptionsViewAny,
            self::SubscriptionsView,
            self::SubscriptionsCreate,
            self::SubscriptionsCancel,
            self::SubscriptionsUpgrade,
            self::PeriodsView,
            self::PeriodsAllocate,
            self::PayoutsViewAny,
            self::PayoutsView,
            self::PayoutsRun,
            self::PayoutsReconcile,
            self::PayoutBatchesViewAny,
            self::PayoutBatchesView,
            self::UsersViewAny,
            self::UsersView,
            self::UsersCreate,
            self::UsersUpdate,
            self::UsersDelete,
            self::RolesViewAny,
            self::RolesView,
            self::RolesUpdate,
        ];
    }
}
