<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use App\Support\Authorization\PermissionName;
use Filament\Widgets\ChartWidget;

class SubscriptionsByTierChart extends ChartWidget
{
    protected ?string $heading = 'Subscriptions by plan tier';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->can(PermissionName::SubscriptionsViewAny) ?? false;
    }

    protected function getData(): array
    {
        $chart = app(AdminDashboardService::class)->subscriptionsByTier();

        return [
            'datasets' => [
                [
                    'label' => 'Subscriptions',
                    'data' => $chart['data'],
                    'backgroundColor' => ['#f59e0b', '#0ea5e9', '#10b981'],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
