<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Transcription;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Services\Transcription\Service\Transcription;

final class TranscriptionServiceTest extends TestCase
{
    /** @var ApiClientInterface&MockObject */
    private ApiClientInterface $apiClient;
    /** @var FileUploader&MockObject */
    private FileUploader $fileUploader;
    private Transcription $transcription;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClientInterface::class);
        $this->fileUploader = $this->createMock(FileUploader::class);
        $this->transcription = new Transcription($this->apiClient, $this->fileUploader);
    }

    public function testGetTranscript(): void
    {
        $fileId = '123e4567-e89b-12d3-a456-426614174000';

        $responseData = [
            'results' => [
                [
                    'file_id' => $fileId,
                    'task_type' => 'transcription',
                    'status' => 'success',
                    'result' => 'Test transcription result',
                ],
            ],
        ];

        $response = $this->createMockResponse($responseData);

        $this->apiClient
            ->expects($this->once())
            ->method('get')
            ->with('/v1/async/transcription', ['file_id' => $fileId])
            ->willReturn($response);

        $transcriptItemResult = $this->transcription->getTranscript($fileId);

        $this->assertSame($fileId, $transcriptItemResult->getFileId());
        $this->assertTrue($transcriptItemResult->isSuccessful());
        $this->assertSame('Test transcription result', $transcriptItemResult->getResult());
    }

    public function testGetTranscriptsList(): void
    {
        $fileIds = [
            '123e4567-e89b-12d3-a456-426614174000',
            '223e4567-e89b-12d3-a456-426614174001',
        ];

        $responseData = [
            'results' => [
                [
                    'file_id' => $fileIds[0],
                    'task_type' => 'transcription',
                    'status' => 'success',
                    'result' => 'Result 1',
                ],
                [
                    'file_id' => $fileIds[1],
                    'task_type' => 'transcription',
                    'status' => 'waiting',
                    'result' => '',
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
        $transcriptBatchResult = $this->transcription->getTranscriptsList($fileIds, $pagination);

        $this->assertCount(2, $transcriptBatchResult->getResults());
        $this->assertSame(1, $transcriptBatchResult->getPage());
        $this->assertFalse($transcriptBatchResult->hasNextPage());
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
