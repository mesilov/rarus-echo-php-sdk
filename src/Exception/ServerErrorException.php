<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns server error
 * HTTP status code: 500 Internal Server Error
 */
class ServerErrorException extends ApiException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = 'Server error',
        array $context = [],
        ?\Throwable $throwable = null
    ) {
        parent::__construct($message, 500, $context, $throwable);
    }
}
