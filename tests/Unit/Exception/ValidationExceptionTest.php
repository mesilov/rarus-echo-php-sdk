<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Exception\ValidationException;

final class ValidationExceptionTest extends TestCase
{
    public function testGetValidationErrors(): void
    {
        $errors = [
            [
                'field' => 'query.page',
                'message' => 'ensure this value is greater than or equal to 1',
                'type' => 'value_error.number.not_ge',
            ],
            [
                'field' => 'body.file_id',
                'message' => 'value is not a valid uuid',
                'type' => 'type_error.uuid',
            ],
        ];

        $validationException = new ValidationException('Validation failed', $errors);

        $this->assertSame('Validation failed', $validationException->getMessage());
        $this->assertSame(422, $validationException->getCode());
        $this->assertSame($errors, $validationException->getValidationErrors());
    }

    public function testGetValidationErrorsAsString(): void
    {
        $errors = [
            [
                'field' => 'query.page',
                'message' => 'must be greater than 1',
                'type' => 'value_error',
            ],
        ];

        $validationException = new ValidationException('Validation failed', $errors);
        $errorString = $validationException->getValidationErrorsAsString();

        $this->assertStringContainsString('query.page', $errorString);
        $this->assertStringContainsString('must be greater than 1', $errorString);
        $this->assertStringContainsString('value_error', $errorString);
    }

    public function testEmptyValidationErrors(): void
    {
        $validationException = new ValidationException('Validation failed');

        $this->assertEmpty($validationException->getValidationErrors());
        $this->assertSame('', $validationException->getValidationErrorsAsString());
    }
}
