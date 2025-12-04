<?php

declare(strict_types=1);

namespace Rarus\Echo\Exception;

/**
 * Exception thrown when API returns validation error
 * HTTP status code: 422
 */
class ValidationException extends EchoException
{
    /**
     * @param array<int, array{field: string, message: string, type: string}> $validationErrors
     */
    public function __construct(
        string $message,
        private readonly array $validationErrors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 422, $previous);
    }

    /**
     * Get validation errors from API response
     *
     * @return array<int, array{field: string, message: string, type: string}>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get validation errors as formatted string
     */
    public function getValidationErrorsAsString(): string
    {
        $errors = [];
        foreach ($this->validationErrors as $error) {
            $errors[] = sprintf(
                "Field '%s': %s (type: %s)",
                $error['field'] ?? 'unknown',
                $error['message'] ?? 'unknown error',
                $error['type'] ?? 'unknown'
            );
        }

        return implode("\n", $errors);
    }
}
