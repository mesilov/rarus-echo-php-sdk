<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Core;

use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Services\Queue\Service\Queue;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Status\Service\Status;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Tests\Integration\IntegrationTestCase;

final class ApiClientTest extends IntegrationTestCase
{
    public function testServiceFactoryFromEnvironmentCreatesApiClientAndServices(): void
    {
        $serviceFactory = $this->createServiceFactory();

        $this->assertInstanceOf(ServiceFactory::class, $serviceFactory);
        $this->assertInstanceOf(ApiClientInterface::class, $serviceFactory->getApiClient());
        $this->assertInstanceOf(Queue::class, $serviceFactory->getQueueService());
        $this->assertInstanceOf(Status::class, $serviceFactory->getStatusService());
        $this->assertInstanceOf(Transcription::class, $serviceFactory->getTranscriptionService());
    }
}
