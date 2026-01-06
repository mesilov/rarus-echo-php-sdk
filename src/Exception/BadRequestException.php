<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns bad request error
 * HTTP status code: 400 Bad Request
 */
class BadRequestException extends ApiException
{
    public function __construct(
        string $message = 'Bad request',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 400, $context, $previous);
    }
}
