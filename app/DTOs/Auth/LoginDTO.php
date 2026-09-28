<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            email: (string) $data['email'],
            password: (string) $data['password'],
        );
    }
}
