<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * Authenticate credentials and issue a fresh Sanctum API token.
     *
     * @return array{user: User, token: string}
     *
     * @throws UserException when credentials are invalid.
     */
    public function login(string $email, string $password): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw UserException::invalidCredentials();
        }

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing(['roles', 'permissions']);

        return [
            'user' => $user,
            'token' => $this->issueToken($user),
        ];
    }

    /**
     * Revoke the user's current Sanctum access token.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Revoke prior tokens and issue a new Sanctum personal access token.
     */
    public function issueToken(User $user, string $name = 'api'): string
    {
        $user->tokens()->delete();

        return $user->createToken($name)->plainTextToken;
    }
}
