<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UserException extends DomainException
{
    /**
     * The user was not found.
     */
    public static function notFound(int $id): self
    {
        return new self(
            "User #{$id} was not found.",
            404,
            'user.not_found',
        );
    }

    /**
     * The user does not have the required instructor role.
     */
    public static function notInstructor(int $id): self
    {
        return new self(
            "User #{$id} is not an instructor.",
            403,
            'user.not_instructor',
        );
    }

    /**
     * User registration failed due to duplicate email.
     */
    public static function duplicateEmail(string $email): self
    {
        return new self(
            "A user with email '{$email}' already exists.",
            409,
            'user.duplicate_email',
        );
    }

    /**
     * Login failed due to invalid credentials.
     */
    public static function invalidCredentials(): self
    {
        return new self(
            'The provided credentials are incorrect.',
            401,
            'user.invalid_credentials',
        );
    }
}
