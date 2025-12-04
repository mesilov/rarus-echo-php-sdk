<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Services\AbstractService;
use Rarus\Echo\Services\Transcription\Request\DriveRequest;
use Rarus\Echo\Services\Transcription\Request\PeriodRequest;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\TranscriptBatchResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptItemResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptPostResult;
use Rarus\Echo\Services\Transcription\Result\WebDAVResult;

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
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getTranscriptsByPeriod(PeriodRequest $request): TranscriptBatchResult
    {
        $this->logger->debug('Getting transcriptions by period', [
            'period_start' => $request->getPeriodStart(),
            'period_end' => $request->getPeriodEnd(),
            'page' => $request->getPage(),
        ]);

        $response = $this->apiClient->get(
            '/v1/async/transcription/period',
            $request->toQueryParams()
        );

        $data = $response->getJson();

        return TranscriptBatchResult::fromArray($data);
    }

    /**
     * Get transcriptions by list of file IDs
     *
     * @param array<string> $fileIds Array of file IDs
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getTranscriptsList(
        array $fileIds,
        int $page = 1,
        int $perPage = 10
    ): TranscriptBatchResult {
        $this->logger->debug('Getting transcriptions list', [
            'file_ids_count' => count($fileIds),
            'page' => $page,
        ]);

        // Convert file IDs to required format
        $body = array_map(
            fn (string $fileId) => ['file_id' => $fileId],
            $fileIds
        );

        $response = $this->apiClient->post(
            '/v2/async/transcription/list',
            $body,
            ['page' => (string) $page, 'per_page' => (string) $perPage]
        );

        $data = $response->getJson();

        return TranscriptBatchResult::fromArray($data);
    }

    /**
     * Submit files from Rarus Drive for transcription
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function submitFromDrive(DriveRequest $request): WebDAVResult
    {
        $this->logger->info('Submitting from Rarus Drive', [
            'target_path' => $request->getTargetPath(),
            'is_immediate' => $request->isImmediate(),
        ]);

        $response = $this->apiClient->post(
            '/v2/webdav',
            $request->toArray(),
            $request->toHeaders()
        );

        $data = $response->getJson();
        $result = WebDAVResult::fromArray($data);

        $this->logger->info('Drive submission completed', [
            'total' => $result->getCount(),
            'successful' => $result->getSuccessCount(),
            'failed' => $result->getFailureCount(),
        ]);

        return $result;
    }
}
