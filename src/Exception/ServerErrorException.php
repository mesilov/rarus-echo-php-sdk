<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns server error
 * HTTP status code: 500 Internal Server Error
 */
class ServerErrorException extends ApiException
{
    public function __construct(
        string $message = 'Server error',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 500, $context, $previous);
    }
}
