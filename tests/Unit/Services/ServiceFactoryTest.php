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
        $this->credentials = Credentials::fromString(
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222'
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
        $this->assertSame('11111111-1111-1111-1111-111111111111', $credentials->getApiKey()->toRfc4122());
        $this->assertSame('22222222-2222-2222-2222-222222222222', $credentials->getUserId()->toRfc4122());
    }

    public function testGetApiClient(): void
    {
        $apiClient = $this->factory->getApiClient();

        $this->assertNotNull($apiClient);
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '33333333-3333-3333-3333-333333333333';
        $_ENV['RARUS_ECHO_USER_ID'] = '44444444-4444-4444-4444-444444444444';

        $factory = ServiceFactory::fromEnvironment();

        $credentials = $factory->getCredentials();
        $this->assertSame('33333333-3333-3333-3333-333333333333', $credentials->getApiKey()->toRfc4122());
        $this->assertSame('44444444-4444-4444-4444-444444444444', $credentials->getUserId()->toRfc4122());

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
