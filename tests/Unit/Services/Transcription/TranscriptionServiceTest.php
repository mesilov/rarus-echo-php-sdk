<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Transcription;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Response\Response;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Service\Transcription;

final class TranscriptionServiceTest extends TestCase
{
    private ApiClient $apiClient;
    private FileUploader $fileUploader;
    private Transcription $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(ApiClient::class);
        $this->fileUploader = $this->createMock(FileUploader::class);
        $this->service = new Transcription($this->apiClient, $this->fileUploader);
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

        $result = $this->service->getTranscript($fileId);

        $this->assertSame($fileId, $result->getFileId());
        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Test transcription result', $result->getResult());
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

        $result = $this->service->getTranscriptsList($fileIds, 1, 10);

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
