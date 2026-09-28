<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ledger;

use App\DTOs\Ledger\FilterLedgerEntryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Instructor\ShowInstructorBalanceRequest;
use App\Http\Requests\Api\V1\Ledger\IndexLedgerEntryRequest;
use App\Http\Resources\Api\V1\Ledger\LedgerEntryResource;
use App\Services\LedgerService;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LedgerEntryController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * List ledger entries for a given instructor, paginated.
     */
    public function index(IndexLedgerEntryRequest $request, int $instructor): JsonResponse
    {
        $this->userService->findInstructorOrFail($instructor);

        $paginator = $this->ledgerService->listEntries(
            FilterLedgerEntryDTO::fromRequest($instructor, $request->validated()),
        );

        return ApiResponse::paginated(
            $paginator,
            LedgerEntryResource::class,
            'Ledger entries retrieved successfully.',
        );
    }

    /**
     * Return the current computed balance for a given instructor.
     */
    public function balance(ShowInstructorBalanceRequest $request, int $instructor): JsonResponse
    {
        $this->userService->findInstructorOrFail($instructor);

        $balanceCents = $this->ledgerService->getBalance($instructor);

        return ApiResponse::success(
            ['balance_cents' => $balanceCents],
            'Instructor balance retrieved successfully.',
        );
    }
}
