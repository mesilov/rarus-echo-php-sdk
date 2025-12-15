<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Services\AbstractService;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\TranscriptBatchResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptItemResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptPostResult;

/**
 * Transcription service
 * Handles all transcription-related operations
 */
final class Transcription extends AbstractService
{
    private readonly LoggerInterface $logger;

    public function __construct(
        ApiClient $apiClient,
        private readonly FileUploader $fileUploader,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($apiClient);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Submit files for transcription
     *
     * @param array<string>        $files   Array of file paths
     * @param TranscriptionOptions $options Transcription options
     *
     * @throws ValidationException
     * @throws FileException
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function submitTranscription(
        array $files,
        TranscriptionOptions $options
    ): TranscriptPostResult {
        $this->logger->info('Submitting files for transcription', [
            'file_count' => count($files),
            'task_type' => $options->getTaskType()->value,
            'language' => $options->getLanguage()->value,
        ]);

        // Prepare files for upload
        $preparedFiles = $this->fileUploader->prepareFiles($files);

        try {
            // Note: Actual multipart implementation would use specific HTTP client features
            // For now, we'll use a simplified approach
            $response = $this->apiClient->post(
                '/v1/async/transcription',
                [],
                $options->toHeaders()
            );

            $data = $response->getJson();
            $result = TranscriptPostResult::fromArray($data);

            $this->logger->info('Files submitted successfully', [
                'file_ids' => $result->getFileIds(),
            ]);

            return $result;
        } finally {
            // Always cleanup file resources
            $this->fileUploader->cleanup($preparedFiles);
        }
    }

    /**
     * Get transcription result by file ID
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function getTranscript(string $fileId): TranscriptItemResult
    {
        $this->logger->debug('Getting transcription', ['file_id' => $fileId]);

        $response = $this->apiClient->get(
            '/v1/async/transcription',
            ['file_id' => $fileId]
        );

        $data = $response->getJson();

        // API returns results array with single item
        $resultData = $data['results'][0] ?? [];

        return TranscriptItemResult::fromArray($resultData);
    }

    /**
     * Get transcriptions by period
     *
     * @param DateTimeInterface $startDate  Start date and time
     * @param DateTimeInterface $endDate    End date and time
     * @param Pagination        $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getTranscriptsByPeriod(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): TranscriptBatchResult {
        $this->logger->debug('Getting transcriptions by period', [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'page' => $pagination->page,
            'per_page' => $pagination->perPage,
        ]);

        $queryParams = [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'time_start' => $startDate->format('H:i:s'),
            'time_end' => $endDate->format('H:i:s'),
            ...$pagination->toQueryParams(),
        ];

        $response = $this->apiClient->get(
            '/v1/async/transcription/period',
            $queryParams
        );

        $data = $response->getJson();

        return TranscriptBatchResult::fromArray($data);
    }

    /**
     * Get transcriptions by list of file IDs
     *
     * @param array<string> $fileIds    Array of file IDs
     * @param Pagination    $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getTranscriptsList(
        array $fileIds,
        Pagination $pagination
    ): TranscriptBatchResult {
        $this->logger->debug('Getting transcriptions list', [
            'file_ids_count' => count($fileIds),
            'page' => $pagination->page,
            'per_page' => $pagination->perPage,
        ]);

        // Convert file IDs to required format
        $body = array_map(
            fn (string $fileId) => ['file_id' => $fileId],
            $fileIds
        );

        $paginationParams = $pagination->toQueryParams();
        $headers = [
            'page' => (string) $paginationParams['page'],
            'per_page' => (string) $paginationParams['per_page'],
        ];

        $response = $this->apiClient->post(
            '/v2/async/transcription/list',
            $body,
            $headers
        );

        $data = $response->getJson();

        return TranscriptBatchResult::fromArray($data);
    }
}
