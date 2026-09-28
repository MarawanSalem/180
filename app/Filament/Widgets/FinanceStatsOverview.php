<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use App\Support\Authorization\PermissionName;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::AdminAccess) ?? false;
    }

    protected function getStats(): array
    {
        $stats = app(AdminDashboardService::class)->overviewStats();

        return [
            Stat::make('Users', number_format($stats['users']))
                ->description($stats['instructors'].' instructors')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
            Stat::make('Active subscriptions', number_format($stats['active_subscriptions']))
                ->description('Currently active plans')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
            Stat::make('Ledger net balance', '$'.number_format($stats['ledger_balance_cents'] / 100, 2))
                ->description('Sum of all ledger entries')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
            Stat::make('Paid payouts', '$'.number_format($stats['paid_payouts_cents'] / 100, 2))
                ->description($stats['uncertain_payouts'].' uncertain')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($stats['uncertain_payouts'] > 0 ? 'danger' : 'success'),
        ];
    }
}
