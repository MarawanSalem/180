<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\DTOs\Subscription\AllocatePeriodDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscription\AllocatePeriodRequest;
use App\Http\Requests\Api\V1\Subscription\ShowPeriodRequest;
use App\Http\Resources\Api\V1\Subscription\SubscriptionPeriodResource;
use App\Models\SubscriptionPeriod;
use App\Services\RevenueAllocationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PeriodAllocationController extends Controller
{
    public function __construct(
        private readonly RevenueAllocationService $revenueAllocationService,
    ) {}

    /**
     * Distribute a subscription period's revenue among instructors.
     */
    public function allocate(AllocatePeriodRequest $request, SubscriptionPeriod $period): JsonResponse
    {
        $dto = AllocatePeriodDTO::fromRequest($request->validated());

        $this->revenueAllocationService->allocatePeriod($period, $dto->consumptionWeights);

        $period->refresh();

        return ApiResponse::success(
            new SubscriptionPeriodResource($period),
            'Period revenue allocated successfully.',
        );
    }

    /**
     * Show a single subscription period (amortization window).
     */
    public function show(ShowPeriodRequest $request, SubscriptionPeriod $period): JsonResponse
    {
        return ApiResponse::success(
            new SubscriptionPeriodResource($period),
            'Subscription period retrieved successfully.',
        );
    }
}
