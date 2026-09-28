<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use App\Support\Authorization\PermissionName;
use Filament\Widgets\ChartWidget;

class PayoutsByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Payouts by status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::PayoutsViewAny) ?? false;
    }

    protected function getData(): array
    {
        $chart = app(AdminDashboardService::class)->payoutsByStatus();

        return [
            'datasets' => [
                [
                    'label' => 'Payouts',
                    'data' => $chart['data'],
                    'backgroundColor' => [
                        '#f59e0b',
                        '#3b82f6',
                        '#22c55e',
                        '#ef4444',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
