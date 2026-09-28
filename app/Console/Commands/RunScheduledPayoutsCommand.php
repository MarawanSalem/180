<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\PayoutException;
use App\Services\PayoutService;
use Illuminate\Console\Command;

/**
 * Initiates the scheduled instructor payout cycle.
 *
 * Concurrency Layers:
 *   Layer 1 — Redis distributed lock (7200s TTL) prevents overlapping executions.
 *   Layer 2 — chunkById(250) streams instructors to cap memory usage.
 *   Layer 3 — Per-instructor pessimistic DB lock inside ProcessInstructorPayoutJob.
 */
class RunScheduledPayoutsCommand extends Command
{
    protected $signature = 'payouts:run';

    protected $description = 'Run the scheduled payout cycle for all eligible instructors.';

    public function __construct(private readonly PayoutService $payoutService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->payoutService->startScheduledCycle();
        } catch (PayoutException $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $batch = $result['batch'];
        $this->info("Batch #{$batch->id} created (UUID: {$batch->batch_uuid}).");
        $this->info("Dispatched {$result['dispatched_jobs']} payout jobs for batch #{$batch->id}.");

        return self::SUCCESS;
    }
}
