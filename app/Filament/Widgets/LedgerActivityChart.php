<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use App\Support\Authorization\PermissionName;
use Filament\Widgets\ChartWidget;

class LedgerActivityChart extends ChartWidget
{
    protected ?string $heading = 'Instructor earnings (last 14 days)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected string $color = 'success';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::LedgerView) ?? false;
    }

    protected function getData(): array
    {
        $chart = app(AdminDashboardService::class)->ledgerEarningsLastDays(14);

        return [
            'datasets' => [
                [
                    'label' => 'Earnings (cents)',
                    'data' => $chart['data'],
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
