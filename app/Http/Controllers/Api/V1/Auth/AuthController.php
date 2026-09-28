<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\LogoutRequest;
use App\Http\Requests\Api\V1\Auth\MeRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthService $authService,
    ) {}

    /**
     * Register a new user account and return a Sanctum access token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->userService->register(
            RegisterDTO::fromRequest($request->validated()),
        );

        $token = $this->authService->issueToken($user);

        return ApiResponse::success(
            array_merge((new UserResource($user))->resolve(), ['token' => $token]),
            'Account registered successfully.',
            201,
        );
    }

    /**
     * Authenticate and return a Sanctum access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginDTO::fromRequest($request->validated());

        $result = $this->authService->login($dto->email, $dto->password);

        return ApiResponse::success(
            array_merge((new UserResource($result['user']))->resolve(), ['token' => $result['token']]),
            'Logged in successfully.',
        );
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(MeRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->loadMissing(['roles', 'permissions']);

        return ApiResponse::success(
            new UserResource($user),
            'Authenticated user retrieved.',
        );
    }

    /**
     * Revoke the current access token and log out.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return ApiResponse::success(null, 'Logged out successfully.');
    }
}
