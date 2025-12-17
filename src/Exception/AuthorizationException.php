<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns authorization error (insufficient permissions)
 * HTTP status code: 403 Forbidden
 */
class AuthorizationException extends EchoException
{
    public function __construct(
        string $message = 'Authorization failed: Insufficient permissions',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }
}
