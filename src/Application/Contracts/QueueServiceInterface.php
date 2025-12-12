<?php

declare(strict_types=1);

namespace Rarus\Echo\Application\Contracts;

use Rarus\Echo\Services\Queue\Result\QueueInfoResult;

/**
 * Contract for Queue service
 */
interface QueueServiceInterface
{
    /**
     * Get aggregated queue information
     */
    public function getQueueInfo(): QueueInfoResult;
}
