<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\EnvironmentEchoClientFactory;
use Rarus\Echo\Infrastructure\Console\SdkEchoClient;

final class EnvironmentEchoClientFactoryTest extends TestCase
{
    private string $temporaryDirectory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir() . '/rarus-echo-cli-' . bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory);
        $this->unsetCredentialEnvironment();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->unsetCredentialEnvironment();

        $envFile = $this->temporaryDirectory . '/.env';
        if (is_file($envFile)) {
            unlink($envFile);
        }

        if (is_dir($this->temporaryDirectory)) {
            rmdir($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function testCreatesSdkClientFromLocalDotEnv(): void
    {
        file_put_contents(
            $this->temporaryDirectory . '/.env',
            implode("\n", [
                'RARUS_ECHO_API_KEY=11111111-1111-1111-1111-111111111111',
                'RARUS_ECHO_USER_ID=22222222-2222-2222-2222-222222222222',
                'RARUS_ECHO_BASE_URL=https://example.com',
            ])
        );

        $factory = new EnvironmentEchoClientFactory($this->temporaryDirectory);

        $this->assertInstanceOf(SdkEchoClient::class, $factory->create());
    }

    public function testCreateFailsWhenCredentialsAreMissing(): void
    {
        $factory = new EnvironmentEchoClientFactory($this->temporaryDirectory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_API_KEY environment variable is not set');

        $factory->create();
    }

    private function unsetCredentialEnvironment(): void
    {
        foreach (['RARUS_ECHO_API_KEY', 'RARUS_ECHO_USER_ID', 'RARUS_ECHO_BASE_URL'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }
}
