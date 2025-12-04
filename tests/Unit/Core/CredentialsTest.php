<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Credentials\Credentials;

final class CredentialsTest extends TestCase
{
    public function testCreateWithValidParameters(): void
    {
        $credentials = Credentials::create(
            'test-api-key',
            '00000000-0000-0000-0000-000000000000'
        );

        $this->assertSame('test-api-key', $credentials->getApiKey());
        $this->assertSame('00000000-0000-0000-0000-000000000000', $credentials->getUserId());
        $this->assertSame('https://production-ai-ui-api.ai.rarus-cloud.ru', $credentials->getBaseUrl());
    }

    public function testCreateWithCustomBaseUrl(): void
    {
        $credentials = Credentials::create(
            'test-api-key',
            '00000000-0000-0000-0000-000000000000',
            'https://custom.example.com/'
        );

        $this->assertSame('https://custom.example.com', $credentials->getBaseUrl());
    }

    public function testCreateThrowsExceptionForEmptyApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('API key cannot be empty');

        Credentials::create('', '00000000-0000-0000-0000-000000000000');
    }

    public function testCreateThrowsExceptionForEmptyUserId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID cannot be empty');

        Credentials::create('test-api-key', '');
    }

    public function testCreateThrowsExceptionForInvalidBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base URL must be a valid URL');

        Credentials::create('test-api-key', '00000000-0000-0000-0000-000000000000', 'not-a-url');
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = 'env-api-key';
        $_ENV['RARUS_ECHO_USER_ID'] = '11111111-1111-1111-1111-111111111111';

        $credentials = Credentials::fromEnvironment();

        $this->assertSame('env-api-key', $credentials->getApiKey());
        $this->assertSame('11111111-1111-1111-1111-111111111111', $credentials->getUserId());

        // Cleanup
        unset($_ENV['RARUS_ECHO_API_KEY'], $_ENV['RARUS_ECHO_USER_ID']);
    }
}
