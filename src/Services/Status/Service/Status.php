<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Service;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\JsonDecoder;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\DateTimeFormatter;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Services\Status\Result\StatusItemListResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Symfony\Component\Uid\Uuid;

/**
 * Status service
 * Handles status checking operations
 */
final readonly class Status
{
    public function __construct(
        private ApiClientInterface $apiClient,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Get status of specific file by ID
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function getByFileId(Uuid $fileId): StatusItemResult
    {
        $this->logger->debug('Getting file status', ['file_id' => $fileId->toRfc4122()]);

        $response = $this->apiClient->get(
            '/v1/async/transcription/fileid',
            ['file_id' => $fileId->toRfc4122()]
        );

        $data = JsonDecoder::decode($response);

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
    public function getByPeriod(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): StatusItemListResult {
        $this->logger->debug('Getting user statuses', [
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
            '/v1/async/transcription/userid',
            $queryParams
        );

        $data = JsonDecoder::decode($response);

        return StatusItemListResult::fromArray($data);
    }

    /**
     * Get statuses by list of file IDs
     *
     * @param array<Uuid> $fileIds    Array of file IDs
     * @param Pagination    $pagination Pagination settings
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function getList(
        array $fileIds,
        Pagination $pagination
    ): StatusItemListResult {
        $this->logger->debug('Getting status list', [
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
            '/v2/async/transcription/fileid/list',
            $body,
            $pagination->toHeaders()
        );

        $data = JsonDecoder::decode($response);

        return StatusItemListResult::fromArray($data);
    }
}
