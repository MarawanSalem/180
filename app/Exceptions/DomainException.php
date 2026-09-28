<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

abstract class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        protected int $statusCode = 400,
        protected ?string $errorCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The HTTP status code this exception maps to.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The machine-readable error code exposed in the JSON envelope.
     * Shape: {entity}.{reason} in snake_case (e.g. "payout.insufficient_balance").
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
