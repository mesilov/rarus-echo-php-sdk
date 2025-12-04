<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use Rarus\Echo\Services\Status\Result\StatusBatchResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Services\Transcription\Request\PeriodRequest;

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
     */
    public function getUserStatuses(PeriodRequest $request): StatusBatchResult;

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
