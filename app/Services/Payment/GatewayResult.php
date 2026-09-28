<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Immutable value object representing a gateway response.
 *
 * Status values:
 *   success  — gateway confirmed funds moved successfully.
 *   failed   — gateway rejected the request (permanent 4xx/5xx).
 *   timeout  — gateway connection dropped before a definitive response.
 */
readonly class GatewayResult
{
    public function __construct(
        public string $status,
        public ?string $providerTxId,
        public ?string $message,
    ) {}

    /**
     * Build a successful gateway result.
     */
    public static function success(string $providerTxId): self
    {
        return new self(
            status: 'success',
            providerTxId: $providerTxId,
            message: null,
        );
    }

    /**
     * Build a permanent failure gateway result.
     */
    public static function failed(string $message): self
    {
        return new self(
            status: 'failed',
            providerTxId: null,
            message: $message,
        );
    }

    /**
     * Build a timeout / uncertain gateway result.
     */
    public static function timeout(): self
    {
        return new self(
            status: 'timeout',
            providerTxId: null,
            message: 'Gateway connection timed out before a definitive response was received.',
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isTimeout(): bool
    {
        return $this->status === 'timeout';
    }
}
