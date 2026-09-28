<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Payout\FilterPayoutBatchDTO;
use App\Exceptions\PayoutException;
use App\Models\PayoutBatch;
use App\Repositories\Interfaces\PayoutBatchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PayoutBatchService
{
    public function __construct(
        private readonly PayoutBatchRepositoryInterface $batches,
    ) {}

    /**
     * Return a paginated list of payout batches.
     */
    public function listBatches(FilterPayoutBatchDTO $filter): LengthAwarePaginator
    {
        return $this->batches->getPaginated($filter->perPage);
    }

    /**
     * Find a payout batch or fail with a domain exception.
     *
     * @throws PayoutException
     */
    public function findOrFail(int $id): PayoutBatch
    {
        $batch = $this->batches->find($id);

        if ($batch === null) {
            throw PayoutException::batchNotFound($id);
        }

        return $batch->loadMissing('payouts');
    }
}
