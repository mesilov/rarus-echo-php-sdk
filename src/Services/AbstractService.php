<?php

declare(strict_types=1);

namespace Rarus\Echo\Services;

use Rarus\Echo\Core\ApiClient;

/**
 * Abstract base class for all services
 * Provides access to API client and common functionality
 */
abstract class AbstractService
{
    public function __construct(
        protected readonly ApiClient $apiClient
    ) {
    }

    /**
     * Get API client
     */
    protected function getApiClient(): ApiClient
    {
        return $this->apiClient;
    }
}
