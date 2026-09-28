<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\FinanceStatsOverview;
use App\Filament\Widgets\LedgerActivityChart;
use App\Filament\Widgets\PayoutsByStatusChart;
use App\Filament\Widgets\SubscriptionsByTierChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            FinanceStatsOverview::class,
            PayoutsByStatusChart::class,
            SubscriptionsByTierChart::class,
            LedgerActivityChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
