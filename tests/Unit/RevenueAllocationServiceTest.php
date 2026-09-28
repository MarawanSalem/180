<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LedgerEntryType;
use App\Enums\SubscriptionPeriodStatus;
use App\Models\InstructorLedgerEntry;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\RevenueAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RevenueAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remainder_allocation_zero_penny_leak(): void
    {
        $student = User::factory()->student()->create();
        $instructors = User::factory()->instructor()->count(3)->create();

        $subscription = Subscription::factory()->monthly()->create([
            'user_id' => $student->id,
            'platform_fee_bps' => 0,
            'price_cents' => 10000,
        ]);

        $period = SubscriptionPeriod::factory()->readyForDistribution()->create([
            'subscription_id' => $subscription->id,
            'period_amount_cents' => 10000,
            'status' => SubscriptionPeriodStatus::Pending->value,
        ]);

        $weights = Collection::make([
            ['instructor_id' => $instructors[0]->id, 'weight' => 1 / 3],
            ['instructor_id' => $instructors[1]->id, 'weight' => 1 / 3],
            ['instructor_id' => $instructors[2]->id, 'weight' => 1 / 3],
        ]);

        app(RevenueAllocationService::class)->allocatePeriod($period, $weights);

        $period->refresh();
        $this->assertSame(SubscriptionPeriodStatus::Distributed, $period->status);

        $amounts = InstructorLedgerEntry::query()
            ->where('subscription_period_id', $period->id)
            ->where('type', LedgerEntryType::Earning->value)
            ->orderByDesc('amount_cents')
            ->pluck('amount_cents')
            ->all();

        $this->assertSame([3334, 3333, 3333], $amounts);
        $this->assertSame(10000, array_sum($amounts));
    }
}
