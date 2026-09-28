<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Enums\PlanTier;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations for the Filament admin dashboard (stats + charts).
 */
class AdminDashboardService
{
    /**
     * @return array{
     *     users: int,
     *     instructors: int,
     *     active_subscriptions: int,
     *     ledger_balance_cents: int,
     *     paid_payouts_cents: int,
     *     uncertain_payouts: int
     * }
     */
    public function overviewStats(): array
    {
        return [
            'users' => User::query()->count(),
            'instructors' => User::query()->where('is_instructor', true)->count(),
            'active_subscriptions' => Subscription::query()->where('status', 'active')->count(),
            'ledger_balance_cents' => (int) InstructorLedgerEntry::query()->sum('amount_cents'),
            'paid_payouts_cents' => (int) Payout::query()->where('status', PayoutStatus::Paid->value)->sum('amount_cents'),
            'uncertain_payouts' => Payout::query()->where('status', PayoutStatus::Uncertain->value)->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function payoutsByStatus(): array
    {
        $counts = Payout::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = [];
        $data = [];

        foreach (PayoutStatus::cases() as $status) {
            $labels[] = $status->value;
            $data[] = (int) ($counts[$status->value] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    public function subscriptionsByTier(): array
    {
        $counts = Subscription::query()
            ->select('plan_tier', DB::raw('count(*) as aggregate'))
            ->groupBy('plan_tier')
            ->pluck('aggregate', 'plan_tier');

        $labels = [];
        $data = [];

        foreach (PlanTier::cases() as $tier) {
            $labels[] = $tier->value;
            $data[] = (int) ($counts[$tier->value] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Daily earning credits for the last N days.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function ledgerEarningsLastDays(int $days = 14): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = InstructorLedgerEntry::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(amount_cents) as total'))
            ->where('type', LedgerEntryType::Earning->value)
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $labels[] = $day;
            $data[] = (int) ($rows[$day] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
