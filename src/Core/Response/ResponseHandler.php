<?php

declare(strict_types=1);

namespace Rarus\Echo\Core\Response;

use Psr\Http\Message\ResponseInterface;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\ValidationException;

/**
 * Handles HTTP responses and converts errors to exceptions
 */
final class ResponseHandler
{
    /**
     * Handle response and throw exceptions for errors
     *
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function handle(ResponseInterface $psrResponse): Response
    {
        $response = new Response($psrResponse);
        $statusCode = $response->getStatusCode();

        // Success responses
        if ($response->isSuccessful()) {
            return $response;
        }

        // Handle error responses
        $this->handleErrorResponse($response, $statusCode);

        return $response;
    }

    /**
     * Handle error response and throw appropriate exception
     *
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    private function handleErrorResponse(Response $response, int $statusCode): void
    {
        $data = [];
        try {
            $data = $response->getJson();
        } catch (\RuntimeException) {
            // If JSON parsing fails, use raw body
        }

        $message = $this->extractErrorMessage($data, $response->getBody());

        match ($statusCode) {
            403 => throw new AuthenticationException($message),
            422 => throw new ValidationException(
                $message,
                $this->extractValidationErrors($data)
            ),
            400, 500 => throw new ApiException(
                $message,
                $statusCode,
                $data
            ),
            default => throw new ApiException(
                $message ?: "HTTP {$statusCode} error",
                $statusCode,
                $data
            ),
        };
    }

    /**
     * Extract error message from response data
     */
    private function extractErrorMessage(array $data, string $fallback): string
    {
        // Try different possible error message fields
        if (isset($data['error']['message'])) {
            return (string) $data['error']['message'];
        }

        if (isset($data['detail'])) {
            return (string) $data['detail'];
        }

        if (isset($data['message'])) {
            return (string) $data['message'];
        }

        return $fallback;
    }

    /**
     * Extract validation errors from 422 response
     *
     * @param array<string, mixed> $data
     *
     * @return array<int, array{field: string, message: string, type: string}>
     */
    private function extractValidationErrors(array $data): array
    {
        $errors = [];

        // Handle different validation error formats
        if (isset($data['error']['data']) && is_array($data['error']['data'])) {
            foreach ($data['error']['data'] as $error) {
                $errors[] = [
                    'field' => $error['field'] ?? 'unknown',
                    'message' => $error['message'] ?? 'unknown error',
                    'type' => $error['type'] ?? 'unknown',
                ];
            }
        } elseif (isset($data['detail']) && is_array($data['detail'])) {
            foreach ($data['detail'] as $error) {
                $errors[] = [
                    'field' => implode('.', $error['loc'] ?? []),
                    'message' => $error['msg'] ?? 'unknown error',
                    'type' => $error['type'] ?? 'unknown',
                ];
            }
        }

        return $errors;
    }
}
