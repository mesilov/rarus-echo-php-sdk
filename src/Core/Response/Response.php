<?php

declare(strict_types=1);

namespace Rarus\Echo\Core\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Wrapper for PSR-7 HTTP response
 * Provides convenient methods to access response data
 */
final class Response
{
    public function __construct(
        private readonly ResponseInterface $psrResponse
    ) {
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->psrResponse->getStatusCode();
    }

    /**
     * Get response body as string
     */
    public function getBody(): string
    {
        return (string) $this->psrResponse->getBody();
    }

    /**
     * Get response body as JSON array
     *
     * @return array<string, mixed>
     */
    public function getJson(): array
    {
        $body = $this->getBody();
        if (empty($body)) {
            return [];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode JSON response: ' . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Get header value
     */
    public function getHeader(string $name): ?string
    {
        if (!$this->psrResponse->hasHeader($name)) {
            return null;
        }

        $values = $this->psrResponse->getHeader($name);

        return $values[0] ?? null;
    }

    /**
     * Check if response is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    /**
     * Get underlying PSR-7 response
     */
    public function getPsrResponse(): ResponseInterface
    {
        return $this->psrResponse;
    }
}
