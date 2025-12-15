<?php

declare(strict_types=1);

namespace Rarus\Echo\Application;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Application\Contracts\QueueServiceInterface;
use Rarus\Echo\Application\Contracts\StatusServiceInterface;
use Rarus\Echo\Application\Contracts\TranscriptionServiceInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Credentials\Credentials;
use Rarus\Echo\Infrastructure\Filesystem\FileHelper;
use Rarus\Echo\Infrastructure\Filesystem\FileUploader;
use Rarus\Echo\Infrastructure\Filesystem\FileValidator;
use Rarus\Echo\Services\Queue\Service\Queue;
use Rarus\Echo\Services\Status\Service\Status;
use Rarus\Echo\Services\Transcription\Service\Transcription;

/**
 * Service factory for Rarus Echo PHP SDK
 * Provides convenient access to all SDK services
 *
 * @example
 * ```php
 * $credentials = Credentials::create('api-key', 'user-id');
 * $factory = new ServiceFactory($credentials);
 *
 * // Use services
 * $transcription = $factory->getTranscriptionService();
 * $result = $transcription->submitTranscription(['/path/to/file.mp3'], $options);
 * ```
 */
final class ServiceFactory
{
    private readonly ApiClient $apiClient;
    private ?TranscriptionServiceInterface $transcriptionService = null;
    private ?StatusServiceInterface $statusService = null;
    private ?QueueServiceInterface $queueService = null;

    /**
     * Create new ServiceFactory instance
     *
     * @param Credentials                    $credentials     API credentials
     * @param ClientInterface|null           $psrClient       PSR-18 HTTP client (auto-discovered if null)
     * @param RequestFactoryInterface|null   $requestFactory  PSR-17 request factory (auto-discovered if null)
     * @param StreamFactoryInterface|null    $streamFactory   PSR-17 stream factory (auto-discovered if null)
     * @param LoggerInterface|null           $logger          PSR-3 logger (NullLogger if null)
     * @param int                            $maxRetries      Maximum number of retry attempts
     * @param int                            $timeout         Request timeout in seconds
     */
    public function __construct(
        private readonly Credentials $credentials,
        ?ClientInterface $psrClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly ?LoggerInterface $logger = null,
        int $maxRetries = 3,
        int $timeout = 120
    ) {
        $this->apiClient = new ApiClient(
            $this->credentials,
            $psrClient,
            $requestFactory,
            $streamFactory,
            $this->logger,
            $maxRetries,
            $timeout
        );
    }

    /**
     * Create ServiceFactory from environment variables
     * Reads RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID from environment
     *
     * @throws \InvalidArgumentException if environment variables are not set
     */
    public static function fromEnvironment(LoggerInterface $logger = new NullLogger()): self
    {
        $credentials = Credentials::fromEnvironment();

        return new self($credentials, logger: $logger);
    }

    /**
     * Get Transcription service
     * Handles file upload and transcription retrieval
     */
    public function getTranscriptionService(): TranscriptionServiceInterface
    {
        if ($this->transcriptionService === null) {
            $fileHelper = new FileHelper();
            $fileValidator = new FileValidator($fileHelper);
            $fileUploader = new FileUploader($fileHelper, $fileValidator);

            $this->transcriptionService = new Transcription(
                $this->apiClient,
                $fileUploader,
                $this->logger
            );
        }

        return $this->transcriptionService;
    }

    /**
     * Get Status service
     * Handles status checking for transcription tasks
     */
    public function getStatusService(): StatusServiceInterface
    {
        if ($this->statusService === null) {
            $this->statusService = new Status(
                $this->apiClient,
                $this->logger
            );
        }

        return $this->statusService;
    }

    /**
     * Get Queue service
     * Provides queue statistics and monitoring
     */
    public function getQueueService(): QueueServiceInterface
    {
        if ($this->queueService === null) {
            $this->queueService = new Queue(
                $this->apiClient,
                $this->logger
            );
        }

        return $this->queueService;
    }

    /**
     * Get API client (for advanced usage)
     */
    public function getApiClient(): ApiClient
    {
        return $this->apiClient;
    }

    /**
     * Get credentials
     */
    public function getCredentials(): Credentials
    {
        return $this->credentials;
    }
}
