<?php

declare(strict_types=1);

namespace Rarus\Echo\Services;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\ApiClient;
use Rarus\Echo\Core\Credentials;
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
    private readonly FileHelper $fileHelper;
    private readonly FileValidator $fileValidator;
    private readonly FileUploader $fileUploader;
    private ?Transcription $transcriptionService = null;
    private ?Status $statusService = null;
    private ?Queue $queueService = null;

    /**
     * Create new ServiceFactory instance
     *
     * @param Credentials                    $credentials     API credentials
     * @param ClientInterface|null           $psrClient       PSR-18 HTTP client (auto-discovered if null)
     * @param RequestFactoryInterface|null   $requestFactory  PSR-17 request factory (auto-discovered if null)
     * @param StreamFactoryInterface|null    $streamFactory   PSR-17 stream factory (auto-discovered if null)
     * @param LoggerInterface|null           $logger          PSR-3 logger (NullLogger if null)
     * @param int                            $timeout         Request timeout in seconds
     * @param FileHelper|null                $fileHelper      File helper (auto-created if null)
     * @param FileValidator|null             $fileValidator   File validator (auto-created if null)
     * @param FileUploader|null              $fileUploader    File uploader (auto-created if null)
     */
    public function __construct(
        private readonly Credentials $credentials,
        ?ClientInterface $psrClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly ?LoggerInterface $logger = null,
        int $timeout = 120,
        ?FileHelper $fileHelper = null,
        ?FileValidator $fileValidator = null,
        ?FileUploader $fileUploader = null
    ) {
        $this->apiClient = new ApiClient(
            $this->credentials,
            $psrClient,
            $requestFactory,
            $streamFactory,
            $this->logger ?? new NullLogger()
        );

        // Initialize filesystem infrastructure
        $this->fileHelper = $fileHelper ?? new FileHelper();
        $this->fileValidator = $fileValidator ?? new FileValidator($this->fileHelper);
        $this->fileUploader = $fileUploader ?? new FileUploader($this->fileHelper, $this->fileValidator);
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
    public function getTranscriptionService(): Transcription
    {
        if ($this->transcriptionService === null) {
            $this->transcriptionService = new Transcription(
                $this->apiClient,
                $this->fileUploader,
                $this->logger
            );
        }

        return $this->transcriptionService;
    }

    /**
     * Get Status service
     * Handles status checking for transcription tasks
     */
    public function getStatusService(): Status
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
    public function getQueueService(): Queue
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
    public function getApiClient(): ApiClientInterface
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
