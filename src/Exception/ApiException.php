<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns an error response
 * HTTP status codes: 400, 500
 */
class ApiException extends EchoException
{
    /**
     * @param array<string, mixed>|null $responseData
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?array $responseData = null,
        ?\Throwable $throwable = null
    ) {
        parent::__construct($message, $statusCode, $throwable);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}
