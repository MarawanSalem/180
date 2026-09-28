<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payout;

use App\DTOs\Payout\FilterPayoutBatchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payout\IndexPayoutBatchRequest;
use App\Http\Requests\Api\V1\Payout\ShowPayoutBatchRequest;
use App\Http\Resources\Api\V1\Payout\PayoutBatchResource;
use App\Models\PayoutBatch;
use App\Services\PayoutBatchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PayoutBatchController extends Controller
{
    public function __construct(
        private readonly PayoutBatchService $payoutBatchService,
    ) {}

    /**
     * List all payout batches, paginated.
     */
    public function index(IndexPayoutBatchRequest $request): JsonResponse
    {
        $paginator = $this->payoutBatchService->listBatches(
            FilterPayoutBatchDTO::fromRequest($request->validated()),
        );

        return ApiResponse::paginated(
            $paginator,
            PayoutBatchResource::class,
            'Payout batches retrieved successfully.',
        );
    }

    /**
     * Show a single payout batch with its payouts.
     */
    public function show(ShowPayoutBatchRequest $request, PayoutBatch $payoutBatch): JsonResponse
    {
        $batch = $this->payoutBatchService->findOrFail((int) $payoutBatch->id);

        return ApiResponse::success(
            new PayoutBatchResource($batch),
            'Payout batch retrieved successfully.',
        );
    }
}
