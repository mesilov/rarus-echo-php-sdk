<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rarus\Echo\Core\Credentials\Credentials;
use Rarus\Echo\Core\Response\Response;
use Rarus\Echo\Core\Response\ResponseHandler;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Infrastructure\HttpClient\HttpClientInterface;
use Rarus\Echo\Infrastructure\HttpClient\PsrHttpClient;
use Rarus\Echo\Infrastructure\HttpClient\RetryMiddleware;

/**
 * Main API client for Rarus Echo service
 * Handles HTTP communication with the API
 */
final class ApiClient
{
    private readonly HttpClientInterface $httpClient;
    private readonly ResponseHandler $responseHandler;

    public function __construct(
        private readonly Credentials $credentials,
        ?ClientInterface $psrClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $maxRetries = 3,
        private readonly int $timeout = 120
    ) {
        // Auto-discover PSR-18 client if not provided
        if ($psrClient === null) {
            $psrClient = \Http\Discovery\Psr18ClientDiscovery::find();
        }

        if ($requestFactory === null) {
            $requestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        }

        if ($streamFactory === null) {
            $streamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
        }

        // Wrap client with retry middleware
        $retryClient = new RetryMiddleware($psrClient, $this->maxRetries, 1000, $this->logger);

        $this->httpClient = new PsrHttpClient($retryClient, $requestFactory, $streamFactory);
        $this->responseHandler = new ResponseHandler();
    }

    /**
     * Send GET request to API
     *
     * @param string                         $endpoint API endpoint (without base URL)
     * @param array<string, string|int|bool> $query    Query parameters
     * @param array<string, string>          $headers  Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function get(string $endpoint, array $query = [], array $headers = []): Response
    {
        $uri = $this->buildUri($endpoint);

        $options = [
            'query' => $query,
            'headers' => $this->buildHeaders($headers),
        ];

        $this->logger->debug('Sending GET request', [
            'uri' => $uri,
            'query' => $query,
        ]);

        $psrResponse = $this->httpClient->get($uri, $options);

        return $this->responseHandler->handle($psrResponse);
    }

    /**
     * Send POST request to API
     *
     * @param string                $endpoint API endpoint (without base URL)
     * @param array<string, mixed>  $body     Request body
     * @param array<string, string> $headers  Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function post(string $endpoint, array $body = [], array $headers = []): Response
    {
        $uri = $this->buildUri($endpoint);

        $options = [
            'body' => $body,
            'headers' => $this->buildHeaders($headers),
        ];

        $this->logger->debug('Sending POST request', [
            'uri' => $uri,
            'body_size' => strlen(json_encode($body) ?: ''),
        ]);

        $psrResponse = $this->httpClient->post($uri, $options);

        return $this->responseHandler->handle($psrResponse);
    }

    /**
     * Send POST request with multipart/form-data
     *
     * @param string                $endpoint API endpoint
     * @param array<string, mixed>  $data     Form data
     * @param array<string, string> $headers  Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function postMultipart(string $endpoint, array $data, array $headers = []): Response
    {
        $uri = $this->buildUri($endpoint);

        // Note: Actual multipart implementation will be handled in FileUploader
        // This is a simplified version
        $options = [
            'body' => $data,
            'headers' => array_merge(
                $this->buildHeaders($headers),
                ['Content-Type' => 'multipart/form-data']
            ),
        ];

        $this->logger->debug('Sending POST multipart request', [
            'uri' => $uri,
        ]);

        $psrResponse = $this->httpClient->post($uri, $options);

        return $this->responseHandler->handle($psrResponse);
    }

    /**
     * Get credentials
     */
    public function getCredentials(): Credentials
    {
        return $this->credentials;
    }

    /**
     * Build full URI from endpoint
     */
    private function buildUri(string $endpoint): string
    {
        $baseUrl = $this->credentials->getBaseUrl();
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * Build request headers
     *
     * @param array<string, string> $additionalHeaders
     *
     * @return array<string, string>
     */
    private function buildHeaders(array $additionalHeaders = []): array
    {
        $headers = [
            'Authorization' => $this->credentials->getApiKey(),
            'user-id' => $this->credentials->getUserId(),
            'Accept' => 'application/json',
        ];

        return array_merge($headers, $additionalHeaders);
    }
}
