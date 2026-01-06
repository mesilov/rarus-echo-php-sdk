<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when file operations fail
 * File not found, access denied, invalid file, etc.
 */
class FileException extends EchoException
{
    public function __construct(
        string $message,
        ?\Throwable $throwable = null
    ) {
        parent::__construct($message, 0, $throwable);
    }
}
