<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payout;

use App\DTOs\Payout\FilterPayoutDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payout\IndexPayoutRequest;
use App\Http\Requests\Api\V1\Payout\ReconcilePayoutsRequest;
use App\Http\Requests\Api\V1\Payout\RunPayoutCycleRequest;
use App\Http\Requests\Api\V1\Payout\ShowPayoutRequest;
use App\Http\Resources\Api\V1\Payout\PayoutBatchResource;
use App\Http\Resources\Api\V1\Payout\PayoutResource;
use App\Jobs\ReconcileUncertainPayoutsJob;
use App\Models\Payout;
use App\Services\PayoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payoutService,
    ) {}

    /**
     * List payouts, paginated, optionally filtered by status and instructor.
     */
    public function index(IndexPayoutRequest $request): JsonResponse
    {
        $paginator = $this->payoutService->listPayouts(
            FilterPayoutDTO::fromRequest($request->validated()),
        );

        return ApiResponse::paginated(
            $paginator,
            PayoutResource::class,
            'Payouts retrieved successfully.',
        );
    }

    /**
     * List payouts for a single instructor.
     */
    public function indexForInstructor(IndexPayoutRequest $request, int $instructor): JsonResponse
    {
        $perPage = (int) ($request->validated()['per_page'] ?? 15);
        $paginator = $this->payoutService->listPayoutsForInstructor($instructor, $perPage);

        return ApiResponse::paginated(
            $paginator,
            PayoutResource::class,
            'Instructor payouts retrieved successfully.',
        );
    }

    /**
     * Show a single payout record.
     */
    public function show(ShowPayoutRequest $request, Payout $payout): JsonResponse
    {
        $payout->loadMissing(['instructor', 'ledgerEntries']);

        return ApiResponse::success(
            new PayoutResource($payout),
            'Payout retrieved successfully.',
        );
    }

    /**
     * Start a scheduled payout cycle (creates a batch and dispatches jobs).
     */
    public function run(RunPayoutCycleRequest $request): JsonResponse
    {
        $result = $this->payoutService->startScheduledCycle();

        return ApiResponse::success(
            array_merge(
                (new PayoutBatchResource($result['batch']))->resolve(),
                ['dispatched_jobs' => $result['dispatched_jobs']],
            ),
            'Payout cycle dispatched successfully.',
            202,
        );
    }

    /**
     * Queue reconciliation of uncertain payouts against the payment gateway.
     */
    public function reconcile(ReconcilePayoutsRequest $request): JsonResponse
    {
        ReconcileUncertainPayoutsJob::dispatch();

        return ApiResponse::success(
            null,
            'Uncertain payout reconciliation queued.',
            202,
        );
    }
}
