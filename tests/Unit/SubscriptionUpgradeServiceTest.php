<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PlanTier;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\SubscriptionUpgradeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SubscriptionUpgradeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prorated_upgrade_preserves_zero_ledger_drift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10'));

        $student = User::factory()->student()->create();
        $instructor = User::factory()->instructor()->create();

        $old = Subscription::factory()->monthly()->create([
            'user_id' => $student->id,
            'price_cents' => 3000,
            'platform_fee_bps' => 0,
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => Carbon::parse('2026-01-31'),
            'status' => SubscriptionStatus::Active->value,
        ]);

        SubscriptionPeriod::factory()->create([
            'subscription_id' => $old->id,
            'period_number' => 1,
            'period_amount_cents' => 3000,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-30',
            'status' => SubscriptionPeriodStatus::Pending->value,
        ]);

        $weights = Collection::make([
            ['instructor_id' => $instructor->id, 'weight' => 1.0],
        ]);

        $new = app(SubscriptionUpgradeService::class)->upgrade(
            old: $old->fresh(['periods', 'user']),
            newTier: PlanTier::Annual,
            newPriceCents: 30000,
            consumptionWeights: $weights,
            upgradeDate: Carbon::parse('2026-01-10'),
        );

        $old->refresh();
        $this->assertSame(SubscriptionStatus::Upgraded, $old->status);

        $activePeriod = $old->periods()->where('period_number', 1)->first();
        $this->assertSame(SubscriptionPeriodStatus::Distributed, $activePeriod->status);
        // Days 1–10 of 30 → floor(3000 * 10/30) = 1000
        $this->assertSame(1000, $activePeriod->period_amount_cents);

        $this->assertSame(SubscriptionStatus::Active, $new->status);
        $this->assertSame(PlanTier::Annual, $new->plan_tier);
        $this->assertSame(30000, $new->price_cents);
        $this->assertSame(12, $new->periods()->count());
        $this->assertSame(30000, (int) $new->periods()->sum('period_amount_cents'));

        // Instructor received only the consumed prorated amount (fee bps = 0).
        $this->assertSame(1000, (int) $instructor->ledgerEntries()->sum('amount_cents'));

        Carbon::setTestNow();
    }
}
