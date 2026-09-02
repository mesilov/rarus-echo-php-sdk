<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\Response\ResponseHandler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

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
    /**
     * Default idle timeout, in seconds, for the auto-discovered HTTP client.
     *
     * Without an explicit value the Symfony HttpClient falls back to PHP's
     * `default_socket_timeout` (~60s), which aborts large multipart uploads
     * with an "Idle timeout reached" error before the API starts responding.
     */
    public const int DEFAULT_HTTP_TIMEOUT_SECONDS = 600;

    /**
     * Environment variable that overrides the default HTTP idle timeout
     * (positive integer number of seconds). Applies only when the HTTP client
     * is auto-discovered (no custom client supplied via withHttpClient()).
     */
    public const string HTTP_TIMEOUT_ENV = 'RARUS_ECHO_HTTP_TIMEOUT';

    private ?ClientInterface $psrClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;
    private ?LoggerInterface $logger = null;
    private ?ResponseHandler $responseHandler = null;
    private ?int $httpTimeout = null;

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
     * Configure the idle timeout, in seconds, for the auto-discovered HTTP client.
     * Ignored when a custom PSR-18 client is supplied via withHttpClient().
     *
     * @throws \InvalidArgumentException when $seconds is not a positive number
     *
     * @return $this
     */
    public function withHttpTimeout(int $seconds): self
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException(
                sprintf('HTTP timeout must be a positive number of seconds, got %d.', $seconds)
            );
        }

        $this->httpTimeout = $seconds;

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
        $psrClient = $this->psrClient ?? $this->createDefaultHttpClient();
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

    /**
     * Create the default PSR-18 HTTP client (Symfony) with an explicit idle
     * timeout so large multipart uploads are not aborted by the ~60s default.
     */
    private function createDefaultHttpClient(): ClientInterface
    {
        return new Psr18Client(
            HttpClient::create(['timeout' => $this->resolveHttpTimeout()])
        );
    }

    /**
     * Resolve the effective HTTP idle timeout: explicit value first, then the
     * RARUS_ECHO_HTTP_TIMEOUT environment variable, then the built-in default.
     */
    private function resolveHttpTimeout(): int
    {
        if ($this->httpTimeout !== null) {
            return $this->httpTimeout;
        }

        return $this->readHttpTimeoutFromEnvironment() ?? self::DEFAULT_HTTP_TIMEOUT_SECONDS;
    }

    /**
     * Read and validate the HTTP idle timeout from the environment.
     *
     * @throws \InvalidArgumentException when the variable is set but not a positive integer
     */
    private function readHttpTimeoutFromEnvironment(): ?int
    {
        $raw = $_ENV[self::HTTP_TIMEOUT_ENV] ?? $_SERVER[self::HTTP_TIMEOUT_ENV] ?? null;

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        if (!ctype_digit($raw) || $raw === '0') {
            throw new \InvalidArgumentException(sprintf(
                '%s must be a positive integer number of seconds, got "%s".',
                self::HTTP_TIMEOUT_ENV,
                $raw
            ));
        }

        return (int) $raw;
    }
}
