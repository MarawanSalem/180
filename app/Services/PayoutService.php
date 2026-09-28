<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Payout\FilterPayoutDTO;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Jobs\ProcessInstructorPayoutJob;
use App\Models\Payout;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Repositories\Interfaces\PayoutBatchRepositoryInterface;
use App\Repositories\Interfaces\PayoutRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayoutService
{
    /**
     * Minimum balance (cents) required to qualify for a payout.
     */
    public const PAYOUT_THRESHOLD_CENTS = 2000;

    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly PayoutBatchRepositoryInterface $batches,
        private readonly LedgerService $ledger,
        private readonly UserService $users,
    ) {}

    /**
     * Create a new payout batch for the current scheduler run.
     */
    public function createBatch(): PayoutBatch
    {
        return $this->batches->create([
            'batch_uuid' => (string) Str::uuid(),
            'status' => PayoutBatchStatus::Processing->value,
            'total_cents' => 0,
        ]);
    }

    /**
     * Create a payout batch and dispatch one job per instructor.
     *
     * @return array{batch: PayoutBatch, dispatched_jobs: int}
     *
     * @throws PayoutException when another cycle holds the execution lock.
     */
    public function startScheduledCycle(): array
    {
        $lock = Cache::lock('payouts_execution_lock', 7200);

        if (! $lock->get()) {
            throw PayoutException::cycleAlreadyRunning();
        }

        try {
            $batch = $this->createBatch();
            $dispatched = 0;

            $this->users->chunkInstructorsById(250, function ($instructors) use ($batch, &$dispatched): void {
                foreach ($instructors as $instructor) {
                    ProcessInstructorPayoutJob::dispatch($instructor, $batch);
                    $dispatched++;
                }
            });

            return [
                'batch' => $batch,
                'dispatched_jobs' => $dispatched,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Return a paginated list of payouts, optionally filtered by status and instructor.
     */
    public function listPayouts(FilterPayoutDTO $filter): LengthAwarePaginator
    {
        return $this->payouts->getPaginated(
            $filter->perPage,
            $filter->status,
            $filter->instructorId,
        );
    }

    /**
     * Return a paginated list of payouts for a single instructor.
     */
    public function listPayoutsForInstructor(int $instructorId, int $perPage = 15): LengthAwarePaginator
    {
        $this->users->findInstructorOrFail($instructorId);

        return $this->payouts->getByInstructorPaginated($instructorId, $perPage);
    }

    /**
     * Process a single instructor payout within a batch.
     * Implements the two-phase commit (withhold → gateway → settle/release).
     *
     * Resumable: if a withhold already exists, Phase 1 is skipped and processing
     * continues at the gateway call. Below-threshold balances mark the payout
     * FAILED and return without throwing or calling the gateway.
     *
     * @param  User  $instructor  The instructor to pay out.
     * @param  PayoutBatch  $batch  The batch this payout belongs to.
     * @param  callable(string $idempotencyKey, int $amountCents): array{status: string, provider_tx_id: string|null, message: string|null}  $gatewayCall
     *                                                                                                                                                     Called outside DB transaction. Must return an array with 'status' key: 'success'|'failed'|'uncertain'.
     *
     * @throws PayoutException when the payout is already Paid.
     */
    public function processInstructorPayout(
        User $instructor,
        PayoutBatch $batch,
        callable $gatewayCall,
    ): Payout {
        $idempotencyKey = "{$batch->batch_uuid}_{$instructor->id}";

        // Phase 1: Pessimistic lock + firstOrCreate + optional withhold — all in one transaction.
        $payout = DB::transaction(function () use ($instructor, $batch, $idempotencyKey): Payout {
            // Lock the instructor row to prevent concurrent payout for same instructor.
            User::where('id', $instructor->id)->lockForUpdate()->first();

            $balanceCents = $this->ledger->getBalance($instructor->id, lockForUpdate: true);

            $payout = $this->payouts->firstOrCreate($idempotencyKey, [
                'payout_batch_id' => $batch->id,
                'instructor_id' => $instructor->id,
                'amount_cents' => $balanceCents,
                'status' => PayoutStatus::Pending->value,
                'idempotency_key' => $idempotencyKey,
                'attempts' => 0,
            ]);

            if ($payout->status === PayoutStatus::Paid) {
                throw PayoutException::alreadyTerminal($payout->id);
            }

            // Terminal Failed (e.g. prior below-threshold) — do not reprocess.
            if ($payout->status === PayoutStatus::Failed) {
                return $payout;
            }

            // Uncertain holds are owned by ReconcileUncertainPayoutsJob — do not re-hit the gateway.
            if ($payout->status === PayoutStatus::Uncertain) {
                return $payout;
            }

            // Scenario B: withhold already booked (crash after Phase 1) — resume at Phase 2.
            if ($this->ledger->hasWithhold($payout)) {
                if ($payout->status !== PayoutStatus::Processing) {
                    $this->payouts->updateStatus($payout, PayoutStatus::Processing);
                }

                return $payout->fresh() ?? $payout;
            }

            // First-time processing attempt: enforce threshold before booking withhold.
            if ($balanceCents < self::PAYOUT_THRESHOLD_CENTS) {
                return $this->payouts->update($payout, [
                    'status' => PayoutStatus::Failed->value,
                    'failure_reason' => sprintf(
                        'Balance of %d cents is below the required payout threshold of %d cents.',
                        $balanceCents,
                        self::PAYOUT_THRESHOLD_CENTS,
                    ),
                ]);
            }

            $this->payouts->updateStatus($payout, PayoutStatus::Processing);
            $this->ledger->bookWithhold($payout);

            return $payout->fresh() ?? $payout;
        });

        // Threshold failure (or prior Failed) — do not call the gateway.
        if ($payout->status === PayoutStatus::Failed) {
            return $payout;
        }

        // Phase 2: Gateway call — outside the DB transaction.
        $this->payouts->incrementAttempts($payout);

        $result = $gatewayCall($idempotencyKey, $payout->amount_cents);

        return match ($result['status']) {
            'success' => $this->handleGatewaySuccess($payout, $result),
            'failed' => $this->handleGatewayFailure($payout, $result),
            default => $this->handleGatewayUncertain($payout),
        };
    }

    /**
     * Mark the payout as paid and post the settled ledger entry.
     *
     * @param  array<string, mixed>  $result
     */
    private function handleGatewaySuccess(Payout $payout, array $result): Payout
    {
        return DB::transaction(function () use ($payout, $result): Payout {
            $this->ledger->settleHold($payout);

            return $this->payouts->update($payout, [
                'status' => PayoutStatus::Paid->value,
                'provider_tx_id' => $result['provider_tx_id'] ?? null,
            ]);
        });
    }

    /**
     * Mark the payout as failed and book an adjustment credit to restore the balance.
     *
     * @param  array<string, mixed>  $result
     */
    private function handleGatewayFailure(Payout $payout, array $result): Payout
    {
        return DB::transaction(function () use ($payout, $result): Payout {
            $this->ledger->releaseHold($payout);

            return $this->payouts->update($payout, [
                'status' => PayoutStatus::Failed->value,
                'failure_reason' => $result['message'] ?? 'Gateway rejected the payout.',
            ]);
        });
    }

    /**
     * Mark the payout as uncertain and leave the withhold intact for reconciliation.
     */
    private function handleGatewayUncertain(Payout $payout): Payout
    {
        return $this->payouts->updateStatus($payout, PayoutStatus::Uncertain);
    }

    /**
     * Finalize a batch: set its status based on payout outcomes and sum paid amounts.
     */
    public function finalizeBatch(PayoutBatch $batch): PayoutBatch
    {
        $batch->load('payouts');
        $payouts = $batch->payouts;

        $hasUncertain = $payouts->contains(fn (Payout $p) => $p->status === PayoutStatus::Uncertain);
        $hasFailed = $payouts->contains(fn (Payout $p) => $p->status === PayoutStatus::Failed);
        $totalPaidCents = $payouts
            ->filter(fn (Payout $p) => $p->status === PayoutStatus::Paid)
            ->sum('amount_cents');

        $finalStatus = ($hasUncertain || $hasFailed)
            ? PayoutBatchStatus::PartiallyFailed
            : PayoutBatchStatus::Completed;

        return $this->batches->update($batch, [
            'status' => $finalStatus->value,
            'total_cents' => $totalPaidCents,
        ]);
    }
}
