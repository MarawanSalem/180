<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlanTier;
use App\Enums\RoleName;
use App\Enums\SubscriptionPeriodStatus;
use App\Enums\SubscriptionStatus;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\RevenueAllocationService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seeds volume demo data across users, subscriptions, periods, ledger, batches, and payouts.
 * Requires RolesAndPermissionsSeeder to have run first.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStaffAccounts();
        $instructors = $this->seedInstructors(12);
        $students = $this->seedStudents(25);
        $this->seedSubscriptionsAndLedger($students, $instructors);
        $this->seedPayoutHistory($instructors);

        $this->command?->info('Demo data seeded (staff, instructors, students, subscriptions, ledger, payouts).');
    }

    private function seedStaffAccounts(): void
    {
        $accounts = [
            ['email' => 'admin@example.com', 'name' => 'Super Admin', 'role' => RoleName::SuperAdmin, 'is_instructor' => false],
            ['email' => 'finance@example.com', 'name' => 'Finance Admin', 'role' => RoleName::FinanceAdmin, 'is_instructor' => false],
            ['email' => 'ops@example.com', 'name' => 'Ops Manager', 'role' => RoleName::Ops, 'is_instructor' => false],
            ['email' => 'instructor@example.com', 'name' => 'Ian Instructor', 'role' => RoleName::Instructor, 'is_instructor' => true],
            ['email' => 'student@example.com', 'name' => 'Sam Student', 'role' => RoleName::Student, 'is_instructor' => false],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'is_instructor' => $account['is_instructor'],
                    'email_verified_at' => now(),
                ],
            );
            $user->syncRoles([$account['role']->value]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function seedInstructors(int $count): Collection
    {
        $named = User::query()->where('email', 'instructor@example.com')->get();

        $extra = User::factory()
            ->count(max(0, $count - $named->count()))
            ->instructor()
            ->create()
            ->each(fn (User $user) => $user->syncRoles([RoleName::Instructor->value]));

        return $named->concat($extra)->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedStudents(int $count): Collection
    {
        $named = User::query()->where('email', 'student@example.com')->get();

        $extra = User::factory()
            ->count(max(0, $count - $named->count()))
            ->student()
            ->create()
            ->each(fn (User $user) => $user->syncRoles([RoleName::Student->value]));

        return $named->concat($extra)->values();
    }

    /**
     * @param  Collection<int, User>  $students
     * @param  Collection<int, User>  $instructors
     */
    private function seedSubscriptionsAndLedger(Collection $students, Collection $instructors): void
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);
        /** @var RevenueAllocationService $allocation */
        $allocation = app(RevenueAllocationService::class);

        $tiers = [
            [PlanTier::Monthly, 3000],
            [PlanTier::ThreeMonth, 9000],
            [PlanTier::Annual, 30000],
        ];

        foreach ($students as $index => $student) {
            if ($student->subscriptions()->exists()) {
                continue;
            }

            [$tier, $price] = $tiers[$index % count($tiers)];

            $subscription = $subscriptionService->createSubscription(
                actor: $student,
                planTier: $tier,
                priceCents: $price,
                platformFeeBps: 3000,
                startsAt: Carbon::now()->subMonths(($index % 6) + 1)->startOfDay(),
            );

            $weights = $this->buildWeights($instructors, $index);

            foreach ($subscription->periods as $period) {
                if ($period->period_end->lt(now()->subDays(2))) {
                    $allocation->allocatePeriod($period->fresh(['subscription']), $weights);
                }
            }
        }

        // Ensure named student has at least one distributed earning for Postman demos.
        $demoStudent = User::query()->where('email', 'student@example.com')->first();
        $demoInstructor = User::query()->where('email', 'instructor@example.com')->first();

        if ($demoStudent && $demoInstructor) {
            $period = SubscriptionPeriod::query()
                ->whereHas('subscription', fn ($q) => $q->where('user_id', $demoStudent->id))
                ->orderBy('id')
                ->first();

            if ($period && $period->status === SubscriptionPeriodStatus::Pending) {
                $allocation->allocatePeriod(
                    $period->fresh(['subscription']),
                    collect([['instructor_id' => $demoInstructor->id, 'weight' => 1.0]]),
                );
            }
        }
    }

    /**
     * @param  Collection<int, User>  $instructors
     * @return Collection<int, array{instructor_id: int, weight: float}>
     */
    private function buildWeights(Collection $instructors, int $seed): Collection
    {
        $slice = $instructors->slice($seed % max(1, $instructors->count() - 2), 3)->values();

        if ($slice->isEmpty()) {
            $slice = $instructors->take(1);
        }

        $share = round(1 / $slice->count(), 4);
        $weights = $slice->map(fn (User $instructor): array => [
            'instructor_id' => (int) $instructor->id,
            'weight' => $share,
        ]);

        // Fix floating remainder on last weight.
        $sum = $weights->sum('weight');
        if (abs(1.0 - $sum) > 0.0001) {
            $last = $weights->count() - 1;
            $weights[$last]['weight'] = round($weights[$last]['weight'] + (1.0 - $sum), 4);
        }

        return $weights->values();
    }

    /**
     * @param  Collection<int, User>  $instructors
     */
    private function seedPayoutHistory(Collection $instructors): void
    {
        $batch = PayoutBatch::query()->firstOrCreate(
            ['batch_uuid' => '11111111-1111-1111-1111-111111111111'],
            [
                'status' => PayoutBatchStatus::Completed->value,
                'total_cents' => 0,
            ],
        );

        $totalPaid = 0;

        foreach ($instructors->take(5) as $instructor) {
            $balance = (int) InstructorLedgerEntry::query()
                ->where('instructor_id', $instructor->id)
                ->sum('amount_cents');

            if ($balance < 2000) {
                InstructorLedgerEntry::query()->create([
                    'instructor_id' => $instructor->id,
                    'payout_id' => null,
                    'subscription_period_id' => null,
                    'type' => LedgerEntryType::Earning->value,
                    'amount_cents' => 5000,
                    'idempotency_key' => 'seed_earning_'.$instructor->id.'_'.Str::random(6),
                    'metadata' => ['seeded' => true],
                    'created_at' => now()->subDays(10),
                ]);
                $balance = 5000;
            }

            $amount = min($balance, 5000);
            $idempotencyKey = $batch->batch_uuid.'_'.$instructor->id;

            $payout = Payout::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'payout_batch_id' => $batch->id,
                    'instructor_id' => $instructor->id,
                    'amount_cents' => $amount,
                    'status' => PayoutStatus::Paid->value,
                    'provider_tx_id' => 'mock_tx_seed_'.$instructor->id,
                    'attempts' => 1,
                ],
            );

            InstructorLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => "payout_{$payout->id}_withhold"],
                [
                    'instructor_id' => $instructor->id,
                    'payout_id' => $payout->id,
                    'subscription_period_id' => null,
                    'type' => LedgerEntryType::PayoutSettled->value,
                    'amount_cents' => -abs($amount),
                    'metadata' => ['seeded' => true],
                    'created_at' => now()->subDays(3),
                ],
            );

            $totalPaid += $amount;
        }

        // A few uncertain / failed samples for Filament filters.
        $sampleInstructor = $instructors->last();
        if ($sampleInstructor) {
            Payout::query()->firstOrCreate(
                ['idempotency_key' => 'seed_uncertain_'.$sampleInstructor->id],
                [
                    'payout_batch_id' => $batch->id,
                    'instructor_id' => $sampleInstructor->id,
                    'amount_cents' => 2500,
                    'status' => PayoutStatus::Uncertain->value,
                    'attempts' => 1,
                ],
            );

            Payout::query()->firstOrCreate(
                ['idempotency_key' => 'seed_failed_'.$sampleInstructor->id],
                [
                    'payout_batch_id' => $batch->id,
                    'instructor_id' => $sampleInstructor->id,
                    'amount_cents' => 1500,
                    'status' => PayoutStatus::Failed->value,
                    'failure_reason' => 'Seeded permanent gateway failure.',
                    'attempts' => 1,
                ],
            );
        }

        $batch->update([
            'status' => PayoutBatchStatus::PartiallyFailed->value,
            'total_cents' => $totalPaid,
        ]);

        // Touch canceled / upgraded subscription samples.
        Subscription::factory()->canceled()->count(3)->create();
        Subscription::factory()->upgraded()->count(2)->create();
        SubscriptionPeriod::factory()->voided()->count(4)->create();
    }
}
