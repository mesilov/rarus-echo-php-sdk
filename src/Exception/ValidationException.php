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
        ?\Throwable $throwable = null
    ) {
        parent::__construct($message, 422, $throwable);
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
        foreach ($this->validationErrors as $validationError) {
            $errors[] = sprintf(
                "Field '%s': %s (type: %s)",
                $validationError['field'] ?? 'unknown',
                $validationError['message'] ?? 'unknown error',
                $validationError['type'] ?? 'unknown'
            );
        }

        return implode("\n", $errors);
    }
}
