<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Queue\Service\Queue;
use Rarus\Echo\Services\Status\Service\Status;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Core\Credentials\Credentials;

final class ServiceFactoryTest extends TestCase
{
    private Credentials $credentials;
    private ServiceFactory $factory;

    protected function setUp(): void
    {
        $this->credentials = Credentials::create(
            'test-api-key',
            '00000000-0000-0000-0000-000000000000'
        );

        $this->factory = new ServiceFactory($this->credentials);
    }

    public function testGetTranscriptionService(): void
    {
        $service = $this->factory->getTranscriptionService();

        $this->assertInstanceOf(Transcription::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->factory->getTranscriptionService());
    }

    public function testGetStatusService(): void
    {
        $service = $this->factory->getStatusService();

        $this->assertInstanceOf(Status::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->factory->getStatusService());
    }

    public function testGetQueueService(): void
    {
        $service = $this->factory->getQueueService();

        $this->assertInstanceOf(Queue::class, $service);

        // Should return same instance (singleton)
        $this->assertSame($service, $this->factory->getQueueService());
    }

    public function testGetCredentials(): void
    {
        $credentials = $this->factory->getCredentials();

        $this->assertSame($this->credentials, $credentials);
        $this->assertSame('test-api-key', $credentials->getApiKey());
        $this->assertSame('00000000-0000-0000-0000-000000000000', $credentials->getUserId());
    }

    public function testGetApiClient(): void
    {
        $apiClient = $this->factory->getApiClient();

        $this->assertNotNull($apiClient);
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = 'env-api-key';
        $_ENV['RARUS_ECHO_USER_ID'] = '11111111-1111-1111-1111-111111111111';

        $factory = ServiceFactory::fromEnvironment();

        $credentials = $factory->getCredentials();
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

        ServiceFactory::fromEnvironment();
    }
}
