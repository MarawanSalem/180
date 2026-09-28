<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Instructor;

use App\DTOs\Instructor\FilterInstructorDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Instructor\IndexInstructorRequest;
use App\Http\Requests\Api\V1\Instructor\ShowInstructorRequest;
use App\Http\Resources\Api\V1\Instructor\InstructorResource;
use App\Services\LedgerService;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class InstructorController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly LedgerService $ledgerService,
    ) {}

    /**
     * List all instructors, paginated.
     */
    public function index(IndexInstructorRequest $request): JsonResponse
    {
        $filter = FilterInstructorDTO::fromRequest($request->validated());
        $paginator = $this->userService->listInstructors($filter->perPage);

        return ApiResponse::paginated(
            $paginator,
            InstructorResource::class,
            'Instructors retrieved successfully.',
        );
    }

    /**
     * Show a single instructor with their current balance.
     */
    public function show(ShowInstructorRequest $request, int $instructor): JsonResponse
    {
        $user = $this->userService->findInstructorOrFail($instructor);

        $user->balance_cents = $this->ledgerService->getBalance((int) $user->id);

        return ApiResponse::success(
            new InstructorResource($user),
            'Instructor retrieved successfully.',
        );
    }
}
