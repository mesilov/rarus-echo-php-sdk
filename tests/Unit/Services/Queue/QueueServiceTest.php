<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Queue;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Services\Queue\Service\Queue;

final class QueueServiceTest extends TestCase
{
    /** @var ApiClientInterface&MockObject */
    private ApiClientInterface $apiClient;
    private Queue $queue;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClientInterface::class);
        $this->queue = new Queue($this->apiClient);
    }

    public function testGetQueueInfo(): void
    {
        $responseData = [
            'results' => [
                [
                    'files_count' => 15.0,
                    'files_size' => 250.5,
                    'files_duration' => 125.3,
                ],
            ],
        ];

        $response = $this->createMockResponse($responseData);

        $this->apiClient
            ->expects($this->once())
            ->method('get')
            ->with('/v1/async/transcription/queue')
            ->willReturn($response);

        $queueInfoResult = $this->queue->getQueueInfo();

        $this->assertSame(15, $queueInfoResult->getFilesCount());
        $this->assertSame(250, $queueInfoResult->getFilesSize());
        $this->assertSame(125, $queueInfoResult->getFilesDuration());
        $this->assertFalse($queueInfoResult->isEmpty());
    }

    public function testGetQueueInfoEmpty(): void
    {
        $responseData = [
            'results' => [
                [
                    'files_count' => 0.0,
                    'files_size' => 0.0,
                    'files_duration' => 0.0,
                ],
            ],
        ];

        $response = $this->createMockResponse($responseData);

        $this->apiClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($response);

        $queueInfoResult = $this->queue->getQueueInfo();

        $this->assertTrue($queueInfoResult->isEmpty());
        $this->assertSame(0, $queueInfoResult->getFilesCount());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMockResponse(array $data): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode($data));

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getStatusCode')->willReturn(200);
        $psrResponse->method('getBody')->willReturn($stream);

        return $psrResponse;
    }
}
