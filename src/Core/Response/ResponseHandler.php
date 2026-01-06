<?php

declare(strict_types=1);

namespace Rarus\Echo\Core\Response;

use Psr\Http\Message\ResponseInterface;
use Rarus\Echo\Core\JsonDecoder;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\AuthorizationException;
use Rarus\Echo\Exception\BadRequestException;
use Rarus\Echo\Exception\ServerErrorException;
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
     * @throws AuthorizationException
     * @throws BadRequestException
     * @throws ValidationException
     * @throws ServerErrorException
     * @throws ApiException
     */
    public function handle(ResponseInterface $psrResponse): ResponseInterface
    {
        $statusCode = $psrResponse->getStatusCode();

        // Success responses
        if ($statusCode >= 200 && $statusCode < 300) {
            return $psrResponse;
        }

        // Handle error responses
        $this->handleErrorResponse($psrResponse, $statusCode);

        return $psrResponse;
    }

    /**
     * Handle error response and throw appropriate exception
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws BadRequestException
     * @throws ValidationException
     * @throws ServerErrorException
     * @throws ApiException
     */
    private function handleErrorResponse(ResponseInterface $response, int $statusCode): void
    {
        $data = [];
        try {
            $data = JsonDecoder::decode($response);
        } catch (\RuntimeException) {
            // If JSON parsing fails, use raw body
        }

        $message = $this->extractErrorMessage($data, (string) $response->getBody());

        match ($statusCode) {
            401 => throw new AuthenticationException($message),
            403 => throw new AuthorizationException($message),
            400 => throw new BadRequestException($message, $data),
            422 => throw new ValidationException(
                $message,
                $this->extractValidationErrors($data)
            ),
            500 => throw new ServerErrorException($message, $data),
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
