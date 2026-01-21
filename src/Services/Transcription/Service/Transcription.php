<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\JsonDecoder;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\DateTimeFormatter;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\FilesTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Symfony\Component\Uid\Uuid;

/**
 * Transcription service
 * Handles all transcription-related operations
 */
final readonly class Transcription
{
    public function __construct(
        private ApiClientInterface $apiClient,
        private FileUploader $fileUploader,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Submit files for transcription
     *
     * @param array<string> $files Array of file paths
     * @param TranscriptionOptions $transcriptionOptions Transcription options
     *
     * @throws ValidationException
     * @throws FileException
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function submit(
        array $files,
        TranscriptionOptions $transcriptionOptions
    ): TranscriptSubmitResult {
        $this->logger->info('Submitting files for transcription', [
            'file_count' => count($files),
            'task_type' => $transcriptionOptions->getTaskType()->value,
            'language' => $transcriptionOptions->getLanguage()->value,
        ]);

        // Prepare files for upload
        $preparedFiles = $this->fileUploader->prepareFiles($files);

        try {
            $response = $this->apiClient->postMultipart(
                '/v1/async/transcription',
                $preparedFiles,
                $transcriptionOptions->toHeaders()
            );

            $data = JsonDecoder::decode($response);
            $result = TranscriptSubmitResult::fromArray($data);

            $this->logger->info('Files submitted successfully', [
                'file_ids' => $result->getFileIds(),
            ]);

            return $result;
        } finally {
            // clean up file resources
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
    public function getByFileId(Uuid $fileId): FileItemTranscriptResult
    {
        $this->logger->debug('Getting transcription', ['file_id' => $fileId]);

        $response = $this->apiClient->get(
            '/v1/async/transcription',
            ['file_id' => $fileId->toRfc4122()]
        );


        $data = JsonDecoder::decode($response);

        // API returns results array with single item
        $resultData = $data['results'][0];
        $resultData['file_id'] = $fileId->toRfc4122();

        return FileItemTranscriptResult::fromArray($resultData);
    }

    /**
     * Get transcriptions by period
     *
     * @param DateTimeInterface $startDate Start date and time
     * @param DateTimeInterface $endDate End date and time
     * @param Pagination $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getByPeriod(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): FilesTranscriptResult {
        $this->logger->debug('Getting transcriptions by period', [
            'period_start' => $startDate->format(DATE_ATOM),
            'period_end' => $endDate->format(DATE_ATOM),
            'page' => $pagination->page,
            'per_page' => $pagination->perPage,
        ]);

        $queryParams = [
            ...DateTimeFormatter::toQueryParams($startDate, $endDate),
            ...$pagination->toQueryParams(),
        ];

        $response = $this->apiClient->get(
            '/v1/async/transcription/period',
            $queryParams
        );

        return FilesTranscriptResult::fromArray(JsonDecoder::decode($response));
    }

    /**
     * Get transcriptions by list of file IDs
     *
     * @param array<Uuid> $fileIds Array of file IDs
     * @param Pagination $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException|\JsonException
     */
    public function getList(
        array $fileIds,
        Pagination $pagination
    ): FilesTranscriptResult {
        $this->logger->debug('Getting transcriptions list', [
            'file_ids_count' => count($fileIds),
            'page' => $pagination->page,
            'per_page' => $pagination->perPage,
        ]);

        // Convert file IDs to required format
        $body = array_map(
            static fn (Uuid $fileId): array => ['file_id' => $fileId->toRfc4122()],
            $fileIds
        );

        $response = $this->apiClient->post(
            '/v2/async/transcription/list',
            $body,
            $pagination->toHeaders()
        );

        $res = JsonDecoder::decode($response);

        return FilesTranscriptResult::fromArray($res);
    }
}
