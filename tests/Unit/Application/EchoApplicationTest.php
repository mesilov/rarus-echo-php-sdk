<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Application\Contracts\QueueServiceInterface;
use Rarus\Echo\Application\Contracts\StatusServiceInterface;
use Rarus\Echo\Application\Contracts\TranscriptionServiceInterface;
use Rarus\Echo\Application\EchoApplication;
use Rarus\Echo\Core\Credentials\Credentials;

final class EchoApplicationTest extends TestCase
{
    private Credentials $credentials;
    private EchoApplication $app;

    protected function setUp(): void
    {
        $this->credentials = Credentials::create(
            'test-api-key',
            '00000000-0000-0000-0000-000000000000'
        );

        $this->app = new EchoApplication($this->credentials);
    }

    public function testGetTranscriptionService(): void
    {
        $service = $this->app->getTranscriptionService();

        $this->assertInstanceOf(TranscriptionServiceInterface::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->app->getTranscriptionService());
    }

    public function testGetStatusService(): void
    {
        $service = $this->app->getStatusService();

        $this->assertInstanceOf(StatusServiceInterface::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->app->getStatusService());
    }

    public function testGetQueueService(): void
    {
        $service = $this->app->getQueueService();

        $this->assertInstanceOf(QueueServiceInterface::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->app->getQueueService());
    }

    public function testGetCredentials(): void
    {
        $credentials = $this->app->getCredentials();

        $this->assertSame($this->credentials, $credentials);
        $this->assertSame('test-api-key', $credentials->getApiKey());
        $this->assertSame('00000000-0000-0000-0000-000000000000', $credentials->getUserId());
    }

    public function testGetApiClient(): void
    {
        $apiClient = $this->app->getApiClient();

        $this->assertNotNull($apiClient);
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = 'env-api-key';
        $_ENV['RARUS_ECHO_USER_ID'] = '11111111-1111-1111-1111-111111111111';

        $app = EchoApplication::fromEnvironment();

        $credentials = $app->getCredentials();
        $this->assertSame('env-api-key', $credentials->getApiKey());
        $this->assertSame('11111111-1111-1111-1111-111111111111', $credentials->getUserId());

        // Cleanup
        unset($_ENV['RARUS_ECHO_API_KEY'], $_ENV['RARUS_ECHO_USER_ID']);
    }

    public function testFromEnvironmentThrowsExceptionWhenVariablesNotSet(): void
    {
        unset($_ENV['RARUS_ECHO_API_KEY'], $_ENV['RARUS_ECHO_USER_ID']);
        unset($_SERVER['RARUS_ECHO_API_KEY'], $_SERVER['RARUS_ECHO_USER_ID']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RARUS_ECHO_API_KEY environment variable is not set');

        EchoApplication::fromEnvironment();
    }
}
