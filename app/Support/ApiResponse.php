<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Pagination\PaginatedPayload;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    /**
     * Return a success envelope for a single resource or scalar payload.
     *
     * @param  JsonResource|array<string, mixed>|null  $data
     */
    public static function success(
        JsonResource|array|null $data,
        string $message,
        int $status = 200,
    ): JsonResponse {
        $resolved = $data instanceof JsonResource ? $data->resolve() : $data;

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resolved,
        ], $status);
    }

    /**
     * Return a paginated success envelope.
     *
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message,
    ): JsonResponse {
        $payload = PaginatedPayload::fromResource($paginator, $resourceClass);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $payload->data,
            'meta' => $payload->meta,
            'links' => $payload->links,
        ]);
    }

    /**
     * Return an error envelope.
     */
    public static function error(
        string $message,
        int $status = 400,
        ?string $errorCode = null,
        ?array $errors = null,
    ): JsonResponse {
        $body = [
            'success' => false,
            'message' => $message,
            'data' => null,
            'error_code' => $errorCode,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }
}
