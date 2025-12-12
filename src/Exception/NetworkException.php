<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when network-related errors occur
 * Connection failures, timeouts, DNS errors, etc.
 */
class NetworkException extends EchoException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
