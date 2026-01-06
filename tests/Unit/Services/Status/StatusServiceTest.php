<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Status;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Services\Status\Service\Status;

final class StatusServiceTest extends TestCase
{
    /** @var ApiClientInterface&MockObject */
    private ApiClientInterface $apiClient;
    private Status $status;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClientInterface::class);
        $this->status = new Status($this->apiClient);
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

        $statusItemResult = $this->status->getFileStatus($fileId);

        $this->assertSame($fileId, $statusItemResult->getFileId());
        $this->assertTrue($statusItemResult->isSuccessful());
        $this->assertSame(10.5, $statusItemResult->getFileSize());
        $this->assertSame(5.2, $statusItemResult->getFileDuration());
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

        $pagination = new Pagination(page: 1, perPage: 10);
        $statusBatchResult = $this->status->getStatusList($fileIds, $pagination);

        $this->assertCount(2, $statusBatchResult->getResults());
        $this->assertSame(1, $statusBatchResult->getPage());
        $this->assertFalse($statusBatchResult->hasNextPage());
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
