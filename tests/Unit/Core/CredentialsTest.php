<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Credentials;

final class CredentialsTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['RARUS_ECHO_API_KEY'], $_ENV['RARUS_ECHO_USER_ID'], $_ENV['RARUS_ECHO_BASE_URL']);
    }

    public function testCreateWithValidParameters(): void
    {
        $credentials = Credentials::fromString(
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222'
        );

        $this->assertSame('11111111-1111-1111-1111-111111111111', $credentials->getApiKey()->toRfc4122());
        $this->assertSame('22222222-2222-2222-2222-222222222222', $credentials->getUserId()->toRfc4122());
        $this->assertSame('https://production-ai-ui-api.ai.rarus-cloud.ru', $credentials->getBaseUrl());
    }

    public function testCreateWithCustomBaseUrl(): void
    {
        $credentials = Credentials::fromString(
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            'https://custom.example.com/'
        );

        $this->assertSame('https://custom.example.com', $credentials->getBaseUrl());
    }

    public function testCreateThrowsExceptionForInvalidBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Base URL must be a valid URL');

        Credentials::fromString(
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            'not-a-url'
        );
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '33333333-3333-3333-3333-333333333333';
        $_ENV['RARUS_ECHO_USER_ID'] = '44444444-4444-4444-4444-444444444444';

        $credentials = Credentials::fromEnvironment();

        $this->assertSame('33333333-3333-3333-3333-333333333333', $credentials->getApiKey()->toRfc4122());
        $this->assertSame('44444444-4444-4444-4444-444444444444', $credentials->getUserId()->toRfc4122());
    }

    public function testFromEnvironmentThrowsExceptionForInvalidApiKeyUuid(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = 'invalid';
        $_ENV['RARUS_ECHO_USER_ID'] = '44444444-4444-4444-4444-444444444444';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_API_KEY is not a valid UUID: invalid');

        Credentials::fromEnvironment();
    }

    public function testFromEnvironmentThrowsExceptionForInvalidUserIdUuid(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '33333333-3333-3333-3333-333333333333';
        $_ENV['RARUS_ECHO_USER_ID'] = 'invalid';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_USER_ID is not a valid UUID: invalid');

        Credentials::fromEnvironment();
    }
}
