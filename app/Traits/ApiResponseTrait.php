<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

trait ApiResponseTrait
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data = null, ?string $message = null, int $code = HttpResponse::HTTP_OK): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ];

        // Remove null values to keep response clean
        $response = array_filter($response, function ($value) {
            return $value !== null;
        });

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string|null $message
     * @param int $code
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $code = HttpResponse::HTTP_BAD_REQUEST, $errors = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error JSON response.
     *
     * @param mixed $errors
     * @param string|null $message
     * @return JsonResponse
     */
    protected function validationErrorResponse($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return a not found JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * Return an unauthorized JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, HttpResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, HttpResponse::HTTP_FORBIDDEN);
    }

    /**
     * Return a created JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function createdResponse($data = null, ?string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, HttpResponse::HTTP_CREATED);
    }

    /**
     * Return a no content JSON response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    protected function noContentResponse(?string $message = 'Operation completed successfully'): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
        ];

        // Remove null message to keep response clean
        if ($message === null) {
            unset($response['message']);
        }

        return response()->json($response, HttpResponse::HTTP_NO_CONTENT);
    }

    /**
     * Return a paginated JSON response.
     *
     * @param mixed $data
     * @param string|null $message
     * @return JsonResponse
     */
    protected function paginatedResponse($data, ?string $message = null): JsonResponse
    {
        $response = [
            'status' => 'success',
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'has_more_pages' => $data->hasMorePages(),
            ],
        ];

        // Remove null message
        if ($message === null) {
            unset($response['message']);
        }

        return response()->json($response, HttpResponse::HTTP_OK);
    }
}
