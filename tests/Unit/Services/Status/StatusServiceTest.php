<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Status;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Response\Response;
use Rarus\Echo\Services\Status\Service\Status;

final class StatusServiceTest extends TestCase
{
    private ApiClient $apiClient;
    private Status $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClient::class);
        $this->service = new Status($this->apiClient);
    }

    public function testGetFileStatus(): void
    {
        $fileId = '123e4567-e89b-12d3-a456-426614174000';

        $responseData = [
            'results' => [
                [
                    'file_id' => $fileId,
                    'status' => 'success',
                    'file_size' => 10.5,
                    'file_duration' => 5.2,
                    'timestamp_arrival' => '2025-01-01T10:00:00+00:00',
                ],
            ],
        ];

        $response = $this->createMockResponse($responseData);

        $this->apiClient
            ->expects($this->once())
            ->method('get')
            ->with('/v1/async/transcription/fileid', ['file_id' => $fileId])
            ->willReturn($response);

        $result = $this->service->getFileStatus($fileId);

        $this->assertSame($fileId, $result->getFileId());
        $this->assertTrue($result->isSuccessful());
        $this->assertSame(10.5, $result->getFileSize());
        $this->assertSame(5.2, $result->getFileDuration());
    }

    public function testGetStatusList(): void
    {
        $fileIds = [
            '123e4567-e89b-12d3-a456-426614174000',
            '223e4567-e89b-12d3-a456-426614174001',
        ];

        $responseData = [
            'results' => [
                [
                    'file_id' => $fileIds[0],
                    'status' => 'success',
                    'file_size' => 10.5,
                    'file_duration' => 5.2,
                    'timestamp_arrival' => '2025-01-01T10:00:00+00:00',
                ],
                [
                    'file_id' => $fileIds[1],
                    'status' => 'waiting',
                    'file_size' => 8.3,
                    'file_duration' => 4.1,
                    'timestamp_arrival' => '2025-01-01T11:00:00+00:00',
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $response = $this->createMockResponse($responseData);

        $this->apiClient
            ->expects($this->once())
            ->method('post')
            ->willReturn($response);

        $result = $this->service->getStatusList($fileIds, 1, 10);

        $this->assertCount(2, $result->getResults());
        $this->assertSame(1, $result->getPage());
        $this->assertFalse($result->hasNextPage());
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
