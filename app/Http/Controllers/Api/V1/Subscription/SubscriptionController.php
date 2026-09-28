<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscription;

use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\DTOs\Subscription\UpgradeSubscriptionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\IndexSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\ShowSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Api\V1\Subscription\UpgradeSubscriptionRequest;
use App\Http\Resources\Api\V1\Subscription\SubscriptionResource;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\SubscriptionUpgradeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionUpgradeService $upgradeService,
    ) {}

    /**
     * List the authenticated user's subscriptions, paginated.
     */
    public function index(IndexSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = (int) ($request->validated()['per_page'] ?? 15);
        $paginator = $this->subscriptionService->listForUser($user, $perPage);

        return ApiResponse::paginated(
            $paginator,
            SubscriptionResource::class,
            'Subscriptions retrieved successfully.',
        );
    }

    /**
     * Create a new subscription for the authenticated user.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $dto = CreateSubscriptionDTO::fromRequest($request->validated());

        $subscription = $this->subscriptionService->createSubscription(
            actor: $user,
            planTier: $dto->planTier,
            priceCents: $dto->priceCents,
            platformFeeBps: $dto->platformFeeBps,
            startsAt: $dto->startsAt,
        );

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription created successfully.',
            201,
        );
    }

    /**
     * Show a single subscription (with its periods).
     */
    public function show(ShowSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $subscription = $this->subscriptionService->loadForShow($subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription retrieved successfully.',
        );
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(CancelSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $subscription = $this->subscriptionService->cancelSubscription($subscription);
        $subscription = $this->subscriptionService->loadForShow($subscription);

        return ApiResponse::success(
            new SubscriptionResource($subscription),
            'Subscription cancelled successfully.',
        );
    }

    /**
     * Upgrade a subscription to a new plan tier.
     */
    public function upgrade(UpgradeSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        $dto = UpgradeSubscriptionDTO::fromRequest($request->validated());

        $newSubscription = $this->upgradeService->upgrade(
            old: $subscription,
            newTier: $dto->newTier,
            newPriceCents: $dto->newPriceCents,
            consumptionWeights: $dto->consumptionWeights,
            upgradeDate: $dto->upgradeDate,
        );

        $newSubscription = $this->subscriptionService->loadForShow($newSubscription);

        return ApiResponse::success(
            new SubscriptionResource($newSubscription),
            'Subscription upgraded successfully.',
        );
    }
}
