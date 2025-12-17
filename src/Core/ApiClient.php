<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
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
use Symfony\Component\Uid\Uuid;

/**
 * Main API client for Rarus Echo service
 * Handles HTTP communication with the API
 */
final class ApiClient
{
    private readonly ClientInterface $psrClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly ResponseHandler $responseHandler;

    public function __construct(
        private readonly Credentials $credentials,
        ?ClientInterface $psrClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        // Auto-discover PSR-18 client if not provided
        $this->psrClient = $psrClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
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
            'options' => $options,
        ]);

        $request = $this->createRequest('GET', $uri, $options);

        try {
            $psrResponse = $this->psrClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException(
                sprintf('HTTP request failed: %s', $e->getMessage()),
                $e
            );
        }

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

        $request = $this->createRequest('POST', $uri, $options);

        try {
            $psrResponse = $this->psrClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException(
                sprintf('HTTP request failed: %s', $e->getMessage()),
                $e
            );
        }

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
     * Create PSR-7 request from options
     *
     * @param array<string,mixed> $options
     *
     * @throws NetworkException
     */
    private function createRequest(string $method, string $uri, array $options): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $uri);

        // Add headers
        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        // Add body
        if (isset($options['body'])) {
            if (is_string($options['body'])) {
                $stream = $this->streamFactory->createStream($options['body']);
                $request = $request->withBody($stream);
            } elseif (is_array($options['body'])) {
                $json = json_encode($options['body']);
                if ($json === false) {
                    throw new NetworkException('Failed to encode body as JSON');
                }
                $stream = $this->streamFactory->createStream($json);
                $request = $request->withBody($stream)
                    ->withHeader('Content-Type', 'application/json');
            }
        }

        // Add query parameters
        if (isset($options['query']) && is_array($options['query'])) {
            $queryString = http_build_query($options['query']);
            $uri = $request->getUri();
            $existingQuery = $uri->getQuery();
            $newQuery = $existingQuery ? $existingQuery . '&' . $queryString : $queryString;
            $request = $request->withUri($uri->withQuery($newQuery));
        }

        return $request;
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
            'Authorization' => $this->credentials->getApiKey()->toRfc4122(),
            'user-id' => $this->credentials->getUserId()->toRfc4122(),
            'Accept' => 'application/json',
        ];

        return array_merge($headers, $additionalHeaders);
    }
}
