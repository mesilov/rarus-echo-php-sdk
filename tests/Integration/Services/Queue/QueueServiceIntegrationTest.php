<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Services\Queue;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;
use Rarus\Echo\Services\Queue\Service\Queue;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Tests\LoggerFactory;

/**
 * Integration tests for Queue service
 *
 * These tests make real API calls to the RARUS Echo service.
 * Required environment variables:
 * - RARUS_ECHO_API_KEY: Your API key (UUID format)
 * - RARUS_ECHO_USER_ID: Your User ID (UUID format)
 * - RARUS_ECHO_BASE_URL: API base URL (optional)
 *
 * Run with: make test-integration-queue
 * Or: docker compose run php-cli vendor/bin/phpunit --testsuite=integration
 */
final class QueueServiceIntegrationTest extends TestCase
{
    private Queue $queue;

    #[\Override]
    protected function setUp(): void
    {
        if (!isset($_ENV['RARUS_ECHO_API_KEY']) || !isset($_ENV['RARUS_ECHO_USER_ID'])) {
            $this->markTestSkipped(
                'Integration tests require RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID environment variables'
            );
        }

        $serviceFactory = ServiceFactory::fromEnvironment(LoggerFactory::defaultStdout());
        $this->queue = $serviceFactory->getQueueService();
    }

    public function testGetQueueInfoReturnsValidResult(): void
    {
        $result = $this->queue->getQueueInfo();

        $this->assertInstanceOf(QueueInfoResult::class, $result);
        $this->assertIsInt($result->filesCount);
        $this->assertIsInt($result->filesSize);
        $this->assertIsInt($result->filesDuration);
        $this->assertGreaterThanOrEqual(0, $result->filesCount);
        $this->assertGreaterThanOrEqual(0, $result->filesSize);
        $this->assertGreaterThanOrEqual(0, $result->filesDuration);
    }

    public function testGetQueueInfoIsEmptyConsistency(): void
    {
        $result = $this->queue->getQueueInfo();

        if ($result->filesCount === 0) {
            $this->assertTrue($result->isEmpty());
        } else {
            $this->assertFalse($result->isEmpty());
        }
    }

    public function testGetQueueInfoToStringFormat(): void
    {
        $result = $this->queue->getQueueInfo();

        $string = $result->toString();
        $expectedPattern = sprintf(
            'Queue: %d files, %d MB, %d minutes',
            $result->filesCount,
            $result->filesSize,
            $result->filesDuration
        );

        $this->assertSame($expectedPattern, $string);
    }

    public function testGetQueueInfoMultipleCalls(): void
    {
        $result1 = $this->queue->getQueueInfo();
        $result2 = $this->queue->getQueueInfo();

        $this->assertInstanceOf(QueueInfoResult::class, $result1);
        $this->assertInstanceOf(QueueInfoResult::class, $result2);

        // Значения могут отличаться, но типы должны быть корректными
        $this->assertIsInt($result1->filesCount);
        $this->assertIsInt($result2->filesCount);
    }
}
