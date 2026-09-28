<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Jobs\ReconcileUncertainPayoutsJob;
use App\Models\InstructorLedgerEntry;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\Payment\GatewayResult;
use App\Services\Payment\GatewayTimeoutException;
use App\Services\Payment\MockPaymentProvider;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PayoutConcurrencyAndResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_payout_job_twice_never_double_pays(): void
    {
        $instructor = User::factory()->instructor()->create();
        $batch = PayoutBatch::factory()->create();

        InstructorLedgerEntry::factory()->create([
            'instructor_id' => $instructor->id,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => 5000,
            'idempotency_key' => 'earning_seed_'.$instructor->id,
        ]);

        $gatewayCalls = 0;
        $gatewayCall = function (string $idempotencyKey, int $amountCents) use (&$gatewayCalls): array {
            $gatewayCalls++;

            return [
                'status' => 'success',
                'provider_tx_id' => 'mock_tx_once',
                'message' => null,
            ];
        };

        /** @var PayoutService $payoutService */
        $payoutService = app(PayoutService::class);

        $first = $payoutService->processInstructorPayout($instructor, $batch, $gatewayCall);
        $this->assertSame(PayoutStatus::Paid, $first->status);
        $this->assertSame(1, $gatewayCalls);

        $this->expectException(\App\Exceptions\PayoutException::class);
        $payoutService->processInstructorPayout($instructor, $batch, $gatewayCall);
    }

    public function test_running_payout_job_twice_never_duplicates_ledger_entries(): void
    {
        $instructor = User::factory()->instructor()->create();
        $batch = PayoutBatch::factory()->create();

        InstructorLedgerEntry::factory()->create([
            'instructor_id' => $instructor->id,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => 5000,
            'idempotency_key' => 'earning_seed_'.$instructor->id,
        ]);

        $gatewayCall = fn (string $key, int $amount): array => [
            'status' => 'success',
            'provider_tx_id' => 'mock_tx_ledger',
            'message' => null,
        ];

        /** @var PayoutService $payoutService */
        $payoutService = app(PayoutService::class);
        $payout = $payoutService->processInstructorPayout($instructor, $batch, $gatewayCall);

        try {
            $payoutService->processInstructorPayout($instructor, $batch, $gatewayCall);
        } catch (\App\Exceptions\PayoutException) {
            // expected on second run
        }

        $this->assertDatabaseCount('payouts', 1);
        $this->assertSame(1, InstructorLedgerEntry::query()
            ->where('payout_id', $payout->id)
            ->where('type', LedgerEntryType::PayoutSettled->value)
            ->count());
        $this->assertSame(0, InstructorLedgerEntry::query()
            ->where('payout_id', $payout->id)
            ->where('type', LedgerEntryType::PayoutWithhold->value)
            ->count());
        $this->assertSame(0, app(LedgerService::class)->getBalance($instructor->id));
    }

    public function test_timeout_sets_status_to_uncertain_without_leaking_money(): void
    {
        $instructor = User::factory()->instructor()->create();
        $batch = PayoutBatch::factory()->create();

        InstructorLedgerEntry::factory()->create([
            'instructor_id' => $instructor->id,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => 4000,
            'idempotency_key' => 'earning_timeout_'.$instructor->id,
        ]);

        $gatewayCall = fn (string $key, int $amount): array => [
            'status' => 'uncertain',
            'provider_tx_id' => null,
            'message' => null,
        ];

        /** @var PayoutService $payoutService */
        $payoutService = app(PayoutService::class);
        $payout = $payoutService->processInstructorPayout($instructor, $batch, $gatewayCall);

        $this->assertSame(PayoutStatus::Uncertain, $payout->status);
        $this->assertSame(0, app(LedgerService::class)->getBalance($instructor->id));
        $this->assertDatabaseHas('instructor_ledger_entries', [
            'payout_id' => $payout->id,
            'type' => LedgerEntryType::PayoutWithhold->value,
            'amount_cents' => -4000,
        ]);
        $this->assertDatabaseMissing('instructor_ledger_entries', [
            'payout_id' => $payout->id,
            'type' => LedgerEntryType::Adjustment->value,
        ]);
    }

    public function test_reconciliation_resolves_uncertain_payout_correctly(): void
    {
        $instructor = User::factory()->instructor()->create();
        $batch = PayoutBatch::factory()->create();

        InstructorLedgerEntry::factory()->create([
            'instructor_id' => $instructor->id,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => 4500,
            'idempotency_key' => 'earning_reconcile_'.$instructor->id,
        ]);

        $payout = Payout::factory()->uncertainStale(10)->create([
            'payout_batch_id' => $batch->id,
            'instructor_id' => $instructor->id,
            'amount_cents' => 4500,
            'idempotency_key' => $batch->batch_uuid.'_'.$instructor->id,
        ]);

        InstructorLedgerEntry::factory()->withhold($instructor->id, $payout->id, 4500)->create();

        $this->mock(MockPaymentProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyStatus')
                ->once()
                ->andReturn(GatewayResult::success('mock_tx_reconciled'));
        });

        (new ReconcileUncertainPayoutsJob)->handle(
            app(\App\Repositories\Interfaces\PayoutRepositoryInterface::class),
            app(LedgerService::class),
            app(MockPaymentProvider::class),
        );

        $payout->refresh();
        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertSame('mock_tx_reconciled', $payout->provider_tx_id);
        $this->assertDatabaseHas('instructor_ledger_entries', [
            'payout_id' => $payout->id,
            'type' => LedgerEntryType::PayoutSettled->value,
            'amount_cents' => -4500,
            'idempotency_key' => "payout_{$payout->id}_withhold",
        ]);
        $this->assertSame(0, app(LedgerService::class)->getBalance($instructor->id));
    }

    public function test_resume_after_withhold_skips_rebooking_and_calls_gateway(): void
    {
        $instructor = User::factory()->instructor()->create();
        $batch = PayoutBatch::factory()->create();

        InstructorLedgerEntry::factory()->create([
            'instructor_id' => $instructor->id,
            'subscription_period_id' => null,
            'type' => LedgerEntryType::Earning->value,
            'amount_cents' => 3000,
            'idempotency_key' => 'earning_resume_'.$instructor->id,
        ]);

        $payout = Payout::factory()->processing()->create([
            'payout_batch_id' => $batch->id,
            'instructor_id' => $instructor->id,
            'amount_cents' => 3000,
            'idempotency_key' => $batch->batch_uuid.'_'.$instructor->id,
        ]);

        InstructorLedgerEntry::factory()->withhold($instructor->id, $payout->id, 3000)->create();

        $calls = 0;
        $gatewayCall = function () use (&$calls): array {
            $calls++;

            return [
                'status' => 'success',
                'provider_tx_id' => 'mock_tx_resume',
                'message' => null,
            ];
        };

        $result = app(PayoutService::class)->processInstructorPayout($instructor, $batch, $gatewayCall);

        $this->assertSame(1, $calls);
        $this->assertSame(PayoutStatus::Paid, $result->status);
        $this->assertSame(1, InstructorLedgerEntry::query()->where('payout_id', $payout->id)->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
