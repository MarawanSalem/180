<?php

declare(strict_types=1);

namespace App\DTOs\Payout;

use App\Enums\PayoutStatus;

readonly class FilterPayoutDTO
{
    public function __construct(
        public ?PayoutStatus $status = null,
        public ?int $instructorId = null,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            status: isset($data['status']) ? PayoutStatus::from((string) $data['status']) : null,
            instructorId: isset($data['instructor_id']) ? (int) $data['instructor_id'] : null,
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }
}
