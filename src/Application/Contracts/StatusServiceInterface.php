<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use DateTimeInterface;
use Rarus\Echo\Services\Status\Result\StatusBatchResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;

/**
 * Contract for Status service
 */
interface StatusServiceInterface
{
    /**
     * Get status of specific file by ID
     */
    public function getFileStatus(string $fileId): StatusItemResult;

    /**
     * Get statuses for user's files by period
     *
     * @param DateTimeInterface $startDate  Start date and time
     * @param DateTimeInterface $endDate    End date and time
     * @param int               $page       Page number (default: 1)
     * @param int               $perPage    Items per page (default: 10)
     */
    public function getUserStatuses(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        int $page = 1,
        int $perPage = 10
    ): StatusBatchResult;

    /**
     * Get statuses by list of file IDs
     *
     * @param array<string> $fileIds
     */
    public function getStatusList(
        array $fileIds,
        int $page = 1,
        int $perPage = 10
    ): StatusBatchResult;
}
