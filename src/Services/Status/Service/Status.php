<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Application\Contracts\StatusServiceInterface;
use Rarus\Echo\Services\AbstractService;
use Rarus\Echo\Services\Status\Result\StatusBatchResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;

/**
 * Status service
 * Handles status checking operations
 */
final class Status extends AbstractService implements StatusServiceInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        ApiClient $apiClient,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($apiClient);
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get status of specific file by ID
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function getFileStatus(string $fileId): StatusItemResult
    {
        $this->logger->debug('Getting file status', ['file_id' => $fileId]);

        $response = $this->apiClient->get(
            '/v1/async/transcription/fileid',
            ['file_id' => $fileId]
        );

        $data = $response->getJson();

        // API returns results array with single item
        $resultData = $data['results'][0] ?? [];

        return StatusItemResult::fromArray($resultData);
    }

    /**
     * Get statuses for user's files by period
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
    public function getUserStatuses(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): StatusBatchResult {
        $this->logger->debug('Getting user statuses', [
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
            '/v1/async/transcription/userid',
            $queryParams
        );

        $data = $response->getJson();

        return StatusBatchResult::fromArray($data);
    }

    /**
     * Get statuses by list of file IDs
     *
     * @param array<string> $fileIds    Array of file IDs
     * @param Pagination    $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getStatusList(
        array $fileIds,
        Pagination $pagination
    ): StatusBatchResult {
        $this->logger->debug('Getting status list', [
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
            '/v2/async/transcription/fileid/list',
            $body,
            $headers
        );

        $data = $response->getJson();

        return StatusBatchResult::fromArray($data);
    }
}
