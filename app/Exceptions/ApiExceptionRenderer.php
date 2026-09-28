<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\MethodNotAllowedException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionRenderer
{
    /**
     * Render any thrown exception into the standard JSON envelope for api/* routes.
     */
    public function render(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: 422,
                errorCode: 'validation_failed',
                errors: $e->errors(),
            );
        }

        if ($e instanceof DomainException) {
            return ApiResponse::error(
                message: $e->getMessage(),
                status: $e->getStatusCode(),
                errorCode: $e->getErrorCode(),
            );
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(
                message: 'Unauthenticated.',
                status: 401,
                errorCode: 'unauthenticated',
            );
        }

        // FormRequest throws AuthorizationException; the framework may wrap it as AccessDeniedHttpException.
        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return ApiResponse::error(
                message: 'This action is unauthorized.',
                status: 403,
                errorCode: 'forbidden',
            );
        }

        // Laravel wraps ModelNotFoundException in NotFoundHttpException before render callbacks.
        $modelNotFound = $this->resolveModelNotFound($e);
        if ($modelNotFound !== null) {
            $model = class_basename($modelNotFound->getModel());
            $errorCode = Str::snake($model).'.not_found';

            return ApiResponse::error(
                message: "{$model} not found.",
                status: 404,
                errorCode: $errorCode,
            );
        }

        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::error(
                message: 'The requested resource was not found.',
                status: 404,
                errorCode: 'not_found',
            );
        }

        if ($e instanceof MethodNotAllowedHttpException || $e instanceof MethodNotAllowedException) {
            return ApiResponse::error(
                message: 'The HTTP method is not allowed for this route.',
                status: 405,
                errorCode: 'method_not_allowed',
            );
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return ApiResponse::error(
                message: 'Too many requests. Please slow down.',
                status: 429,
                errorCode: 'too_many_requests',
            );
        }

        return ApiResponse::error(
            message: 'An unexpected error occurred. Please try again later.',
            status: 500,
            errorCode: 'server_error',
        );
    }

    /**
     * Unwrap ModelNotFoundException whether thrown directly or wrapped by the framework.
     */
    private function resolveModelNotFound(Throwable $e): ?ModelNotFoundException
    {
        if ($e instanceof ModelNotFoundException) {
            return $e;
        }

        $previous = $e->getPrevious();

        return $previous instanceof ModelNotFoundException ? $previous : null;
    }
}
