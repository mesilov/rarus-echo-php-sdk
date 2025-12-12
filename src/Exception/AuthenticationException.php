<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns authentication error
 * HTTP status code: 403
 */
class AuthenticationException extends EchoException
{
    public function __construct(
        string $message = 'Authentication failed: Invalid API key',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }
}
