<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use DateTimeInterface;
use Rarus\Echo\Core\Pagination;
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
     * @param Pagination        $pagination Pagination settings
     */
    public function getUserStatuses(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate,
        Pagination $pagination
    ): StatusBatchResult;

    /**
     * Get statuses by list of file IDs
     *
     * @param array<string> $fileIds    Array of file IDs
     * @param Pagination    $pagination Pagination settings
     */
    public function getStatusList(
        array $fileIds,
        Pagination $pagination
    ): StatusBatchResult;
}
