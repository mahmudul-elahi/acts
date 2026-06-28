<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

trait RespondsWithApiJson
{
    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        int $status = HttpResponse::HTTP_OK,
        array $meta = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $headers
     */
    protected function createdResponse(
        mixed $data = null,
        ?string $message = 'Created successfully.',
        array $meta = [],
        array $headers = [],
    ): JsonResponse {
        return $this->successResponse(
            data: $data,
            message: $message,
            status: HttpResponse::HTTP_CREATED,
            meta: $meta,
            headers: $headers,
        );
    }

    protected function paginatedResponse(
        ResourceCollection $resource,
        ?string $message = null,
        array $headers = [],
    ): JsonResponse {
        $paginator = $resource->resource;

        return $this->successResponse(
            data: $resource,
            message: $message,
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            headers: $headers,
        );
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    protected function errorResponse(
        string $message,
        int $status = HttpResponse::HTTP_BAD_REQUEST,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status, $headers);
    }
}
