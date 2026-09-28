<?php

declare(strict_types=1);

namespace App\DTOs\Payout;

readonly class FilterPayoutBatchDTO
{
    public function __construct(
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
