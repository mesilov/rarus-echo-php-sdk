<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Queue\Service;

use Psr\Log\LoggerInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\JsonDecoder;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;

/**
 * Queue service
 * Handles queue information operations
 */
final readonly class Queue
{
    public function __construct(private ApiClientInterface $apiClient, private LoggerInterface $logger)
    {
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

        $data = JsonDecoder::decode($response);
        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->logger->debug('Queue info retrieved', [
            'files_count' => $queueInfoResult->filesCount,
            'files_size_mb' => $queueInfoResult->filesSize,
            'files_duration_min' => $queueInfoResult->filesDuration,
        ]);

        return $queueInfoResult;
    }
}
