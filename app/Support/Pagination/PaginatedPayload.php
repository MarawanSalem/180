<?php

declare(strict_types=1);

namespace App\Support\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginatedPayload
{
    /** @var array<int, mixed> */
    public readonly array $data;

    /** @var array{current_page: int, last_page: int, per_page: int, total: int} */
    public readonly array $meta;

    /** @var array{first: string|null, last: string|null, prev: string|null, next: string|null} */
    public readonly array $links;

    /**
     * Build pagination envelope from a paginator and a resolved resource collection.
     *
     * @param  array<int, mixed>  $data  Already-resolved resource collection items
     */
    public function __construct(LengthAwarePaginator $paginator, array $data)
    {
        $this->data = $data;

        $this->meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        $this->links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * Build a PaginatedPayload by resolving a resource class over the paginator items.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function fromResource(LengthAwarePaginator $paginator, string $resourceClass): self
    {
        $data = $paginator->getCollection()
            ->map(fn ($item) => (new $resourceClass($item))->resolve())
            ->values()
            ->all();

        return new self($paginator, $data);
    }
}
