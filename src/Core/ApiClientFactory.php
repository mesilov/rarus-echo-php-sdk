<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\Response\ResponseHandler;

/**
 * Factory for creating ApiClient instances with fluent configuration
 *
 * @example
 * ```php
 * // Create from environment variables
 * $apiClient = ApiClientFactory::fromEnvironment()->build();
 *
 * // Create with custom configuration
 * $apiClient = (new ApiClientFactory($credentials))
 *     ->withLogger($logger)
 *     ->withHttpClient($customClient)
 *     ->build();
 * ```
 */
final class ApiClientFactory
{
    private ?ClientInterface $psrClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;
    private ?LoggerInterface $logger = null;
    private ?ResponseHandler $responseHandler = null;

    /**
     * Create factory with credentials
     */
    public function __construct(
        private readonly Credentials $credentials
    ) {
    }

    /**
     * Create factory from environment variables
     * Reads RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID from environment
     *
     * @throws \InvalidArgumentException if environment variables are not set or invalid
     */
    public static function fromEnvironment(): self
    {
        $credentials = Credentials::fromEnvironment();

        return new self($credentials);
    }

    /**
     * Configure PSR-18 HTTP client
     * If not set, will auto-discover using php-http/discovery
     *
     * @return $this
     */
    public function withHttpClient(ClientInterface $psrClient): self
    {
        $this->psrClient = $psrClient;

        return $this;
    }

    /**
     * Configure PSR-17 request factory
     * If not set, will auto-discover using php-http/discovery
     *
     * @return $this
     */
    public function withRequestFactory(RequestFactoryInterface $requestFactory): self
    {
        $this->requestFactory = $requestFactory;

        return $this;
    }

    /**
     * Configure PSR-17 stream factory
     * If not set, will auto-discover using php-http/discovery
     *
     * @return $this
     */
    public function withStreamFactory(StreamFactoryInterface $streamFactory): self
    {
        $this->streamFactory = $streamFactory;

        return $this;
    }

    /**
     * Configure PSR-3 logger
     * If not set, will use NullLogger
     *
     * @return $this
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Configure response handler
     * If not set, will create default ResponseHandler
     *
     * @internal This is primarily for testing purposes
     *
     * @return $this
     */
    public function withResponseHandler(ResponseHandler $responseHandler): self
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }

    /**
     * Build configured ApiClient instance
     * Performs auto-discovery for any unset PSR dependencies
     *
     * @throws NotFoundException if PSR implementations not found
     */
    public function build(): ApiClient
    {
        // Auto-discover PSR dependencies if not set
        $psrClient = $this->psrClient ?? Psr18ClientDiscovery::find();
        $requestFactory = $this->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = $this->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $logger = $this->logger ?? new NullLogger();
        $responseHandler = $this->responseHandler ?? new ResponseHandler();

        return new ApiClient(
            credentials: $this->credentials,
            psrClient: $psrClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            logger: $logger,
            responseHandler: $responseHandler
        );
    }
}
