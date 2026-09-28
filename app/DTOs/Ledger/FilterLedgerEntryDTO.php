<?php

declare(strict_types=1);

namespace App\DTOs\Ledger;

use App\Enums\LedgerEntryType;

readonly class FilterLedgerEntryDTO
{
    public function __construct(
        public int $instructorId,
        public ?LedgerEntryType $type = null,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(int $instructorId, array $data): self
    {
        return new self(
            instructorId: $instructorId,
            type: isset($data['type']) ? LedgerEntryType::from((string) $data['type']) : null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
