<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Services\Queue\Service\Queue;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Status\Service\Status;
use Rarus\Echo\Services\Transcription\Service\Transcription;

final class ServiceFactoryTest extends TestCase
{
    private Credentials $credentials;
    private ServiceFactory $serviceFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->credentials = Credentials::fromString(
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222'
        );

        $this->serviceFactory = new ServiceFactory($this->credentials);
    }

    public function testGetTranscriptionService(): void
    {
        $transcription = $this->serviceFactory->getTranscriptionService();

        $this->assertInstanceOf(Transcription::class, $transcription);

        // Should return same instance (singleton)
        $this->assertSame($transcription, $this->serviceFactory->getTranscriptionService());
    }

    public function testGetStatusService(): void
    {
        $status = $this->serviceFactory->getStatusService();

        $this->assertInstanceOf(Status::class, $status);

        // Should return same instance (singleton)
        $this->assertSame($status, $this->serviceFactory->getStatusService());
    }

    public function testGetQueueService(): void
    {
        $queue = $this->serviceFactory->getQueueService();

        $this->assertInstanceOf(Queue::class, $queue);

        // Should return same instance (singleton)
        $this->assertSame($queue, $this->serviceFactory->getQueueService());
    }

    public function testGetCredentials(): void
    {
        $credentials = $this->serviceFactory->getCredentials();

        $this->assertSame($this->credentials, $credentials);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $credentials->getApiKey()->toRfc4122());
        $this->assertSame('22222222-2222-2222-2222-222222222222', $credentials->getUserId()->toRfc4122());
    }

    public function testGetApiClient(): void
    {
        $apiClient = $this->serviceFactory->getApiClient();

        $this->assertNotNull($apiClient);
    }

    public function testFromEnvironment(): void
    {
        $_ENV['RARUS_ECHO_API_KEY'] = '33333333-3333-3333-3333-333333333333';
        $_ENV['RARUS_ECHO_USER_ID'] = '44444444-4444-4444-4444-444444444444';

        $serviceFactory = ServiceFactory::fromEnvironment();

        $credentials = $serviceFactory->getCredentials();
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
