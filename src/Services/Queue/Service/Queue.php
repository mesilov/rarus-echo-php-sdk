<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Queue\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;

/**
 * Queue service
 * Handles queue information operations
 */
final class Queue
{
    private readonly LoggerInterface $logger;

    public function __construct(
        protected readonly ApiClient $apiClient,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get aggregated queue information
     * Returns statistics about all files in the transcription queue
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ApiException
     */
    public function getQueueInfo(): QueueInfoResult
    {
        $this->logger->debug('Getting queue information');

        $response = $this->apiClient->get('/v1/async/transcription/queue');

        $data = $response->getJson();
        $result = QueueInfoResult::fromArray($data);

        $this->logger->debug('Queue info retrieved', [
            'files_count' => $result->getFilesCount(),
            'files_size_mb' => $result->getFilesSize(),
            'files_duration_min' => $result->getFilesDuration(),
        ]);

        return $result;
    }
}
