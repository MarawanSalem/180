<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public bool $isInstructor,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            isInstructor: (bool) ($data['is_instructor'] ?? false),
        );
    }
}
