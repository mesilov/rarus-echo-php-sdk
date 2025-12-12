<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use Carbon\CarbonPeriod;
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
     * @param CarbonPeriod $period     Date period (start and end dates)
     * @param string       $timeStart  Start time (default: '00:00:00')
     * @param string       $timeEnd    End time (default: '23:59:59')
     * @param int          $page       Page number (default: 1)
     * @param int          $perPage    Items per page (default: 10)
     */
    public function getUserStatuses(
        CarbonPeriod $period,
        string $timeStart = '00:00:00',
        string $timeEnd = '23:59:59',
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
