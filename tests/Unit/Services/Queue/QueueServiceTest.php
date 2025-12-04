<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Queue;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Response\Response;
use Rarus\Echo\Services\Queue\Service\Queue;

final class QueueServiceTest extends TestCase
{
    private ApiClient $apiClient;
    private Queue $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClient::class);
        $this->service = new Queue($this->apiClient);
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

        $result = $this->service->getQueueInfo();

        $this->assertSame(15.0, $result->getFilesCount());
        $this->assertSame(250.5, $result->getFilesSize());
        $this->assertSame(125.3, $result->getFilesDuration());
        $this->assertFalse($result->isEmpty());
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

        $result = $this->service->getQueueInfo();

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0.0, $result->getFilesCount());
    }

    private function createMockResponse(array $data): Response
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode($data));

        $psrResponse = $this->createMock(ResponseInterface::class);
        $psrResponse->method('getStatusCode')->willReturn(200);
        $psrResponse->method('getBody')->willReturn($stream);

        return new Response($psrResponse);
    }
}
