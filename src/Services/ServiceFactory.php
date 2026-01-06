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
use Rarus\Echo\Core\ApiClientFactory;
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
    private readonly FileValidator $fileValidator;
    private readonly FileUploader $fileUploader;
    private ?Transcription $transcription = null;
    private ?Status $status = null;
    private ?Queue $queue = null;

    /**
     * Create new ServiceFactory instance
     *
     * @param Credentials                    $credentials     API credentials
     * @param ClientInterface|null           $psrClient       PSR-18 HTTP client (auto-discovered if null)
     * @param RequestFactoryInterface|null   $requestFactory  PSR-17 request factory (auto-discovered if null)
     * @param StreamFactoryInterface|null    $streamFactory   PSR-17 stream factory (auto-discovered if null)
     * @param LoggerInterface|null           $logger          PSR-3 logger (NullLogger if null)
     * @param FileHelper                     $fileHelper      File helper
     * @param FileValidator|null             $fileValidator   File validator (auto-created if null)
     * @param FileUploader|null              $fileUploader    File uploader (auto-created if null)
     */
    public function __construct(
        private readonly Credentials $credentials,
        ?ClientInterface $psrClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly FileHelper $fileHelper = new FileHelper(),
        ?FileValidator $fileValidator = null,
        ?FileUploader $fileUploader = null
    ) {
        // Build ApiClient using factory
        $factory = new ApiClientFactory($this->credentials);

        if ($psrClient instanceof ClientInterface) {
            $factory = $factory->withHttpClient($psrClient);
        }

        if ($requestFactory instanceof RequestFactoryInterface) {
            $factory = $factory->withRequestFactory($requestFactory);
        }

        if ($streamFactory instanceof StreamFactoryInterface) {
            $factory = $factory->withStreamFactory($streamFactory);
        }

        if ($this->logger instanceof LoggerInterface) {
            $factory = $factory->withLogger($this->logger);
        }

        $this->apiClient = $factory->build();

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
        if (!$this->transcription instanceof Transcription) {
            $this->transcription = new Transcription(
                $this->apiClient,
                $this->fileUploader,
                $this->logger ?? new NullLogger()
            );
        }

        return $this->transcription;
    }

    /**
     * Get Status service
     * Handles status checking for transcription tasks
     */
    public function getStatusService(): Status
    {
        if (!$this->status instanceof Status) {
            $this->status = new Status(
                $this->apiClient,
                $this->logger ?? new NullLogger()
            );
        }

        return $this->status;
    }

    /**
     * Get Queue service
     * Provides queue statistics and monitoring
     */
    public function getQueueService(): Queue
    {
        if (!$this->queue instanceof Queue) {
            $this->queue = new Queue(
                $this->apiClient,
                $this->logger ?? new NullLogger()
            );
        }

        return $this->queue;
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
