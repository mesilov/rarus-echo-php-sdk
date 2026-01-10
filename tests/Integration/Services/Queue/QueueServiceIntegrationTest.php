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
        $queueInfoResult = $this->queue->getQueueInfo();

        $this->assertInstanceOf(QueueInfoResult::class, $queueInfoResult);
        $this->assertIsInt($queueInfoResult->filesCount);
        $this->assertIsInt($queueInfoResult->filesSize);
        $this->assertIsInt($queueInfoResult->filesDuration);
        $this->assertGreaterThanOrEqual(0, $queueInfoResult->filesCount);
        $this->assertGreaterThanOrEqual(0, $queueInfoResult->filesSize);
        $this->assertGreaterThanOrEqual(0, $queueInfoResult->filesDuration);
    }

    public function testGetQueueInfoIsEmptyConsistency(): void
    {
        $queueInfoResult = $this->queue->getQueueInfo();

        if ($queueInfoResult->filesCount === 0) {
            $this->assertTrue($queueInfoResult->isEmpty());
        } else {
            $this->assertFalse($queueInfoResult->isEmpty());
        }
    }

    public function testGetQueueInfoToStringFormat(): void
    {
        $queueInfoResult = $this->queue->getQueueInfo();

        $string = $queueInfoResult->toString();
        $expectedPattern = sprintf(
            'Queue: %d files, %d MB, %d minutes',
            $queueInfoResult->filesCount,
            $queueInfoResult->filesSize,
            $queueInfoResult->filesDuration
        );

        $this->assertSame($expectedPattern, $string);
    }

    public function testGetQueueInfoMultipleCalls(): void
    {
        $queueInfoResult = $this->queue->getQueueInfo();
        $result2 = $this->queue->getQueueInfo();

        $this->assertInstanceOf(QueueInfoResult::class, $queueInfoResult);
        $this->assertInstanceOf(QueueInfoResult::class, $result2);

        // Значения могут отличаться, но типы должны быть корректными
        $this->assertIsInt($queueInfoResult->filesCount);
        $this->assertIsInt($result2->filesCount);
    }
}
