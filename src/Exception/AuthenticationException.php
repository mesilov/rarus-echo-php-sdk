<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns authentication error (invalid credentials)
 * HTTP status code: 401 Unauthorized
 */
class AuthenticationException extends EchoException
{
    public function __construct(
        string $message = 'Authentication failed: Invalid credentials',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 401, $previous);
    }
}
