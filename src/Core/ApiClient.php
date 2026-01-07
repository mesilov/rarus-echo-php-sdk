<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Rarus\Echo\Contracts\ApiClientInterface;
use Rarus\Echo\Core\Response\ResponseHandler;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\AuthorizationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * Main API client for Rarus Echo service
 * Handles HTTP communication with the API
 */
final readonly class ApiClient implements ApiClientInterface
{
    public function __construct(
        private Credentials $credentials,
        private ClientInterface $psrClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
        private ResponseHandler $responseHandler,
    ) {
    }

    /**
     * Send GET request to API
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param array<string, string|int|bool> $query Query parameters
     * @param array<string, string> $headers Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthorizationException
     */
    #[\Override]
    public function get(string $endpoint, array $query = [], array $headers = []): ResponseInterface
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
    #[\Override]
    public function post(string $endpoint, array $body = [], array $headers = []): ResponseInterface
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
     * Send POST request with multipart/form-data
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param array<int, array{name: string, contents: resource, filename: string, headers: array<string, string>}> $files Files prepared for upload
     * @param array<string, string> $headers Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    #[\Override]
    public function postMultipart(string $endpoint, array $files, array $headers = []): ResponseInterface
    {
        $uri = $this->buildUri($endpoint);

        $this->logger->debug('Sending POST multipart request', [
            'uri' => $uri,
            'files_count' => count($files),
        ]);

        // Build flat array structure for FormDataPart to create multiple fields with same name
        // Each element is a single-key array: ['files' => DataPart]
        // This creates multiple "files" fields instead of "files[0]", "files[1]"
        $formFields = [];

        foreach ($files as $file) {
            // Read file content
            if (is_resource($file['contents'])) {
                rewind($file['contents']);
                $fileContent = stream_get_contents($file['contents']);
                if ($fileContent === false) {
                    throw new NetworkException('Failed to read file content from resource');
                }
            } else {
                $fileContent = $file['contents'];
            }

            // Create DataPart for this file
            $dataPart = new DataPart(
                $fileContent,
                $file['filename'],
                $file['headers']['Content-Type']
            );

            // Add as single-element array - this is key for duplicate field names
            $formFields[] = [$file['name'] => $dataPart];
        }

        // Create FormDataPart from flat array structure
        $formData = new FormDataPart($formFields);

        // Create request
        $request = $this->requestFactory->createRequest('POST', $uri);

        // Add auth headers
        $authHeaders = $this->buildHeaders($headers);
        foreach ($authHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Add FormDataPart headers (includes Content-Type with boundary)
        $preparedHeaders = $formData->getPreparedHeaders();
        foreach ($preparedHeaders->toArray() as $header) {
            [$name, $value] = explode(':', $header, 2);
            $request = $request->withHeader(trim($name), trim($value));
        }

        // Add body as stream
        $body = $formData->bodyToString();
        $stream = $this->streamFactory->createStream($body);
        $request = $request->withBody($stream);

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
    #[\Override]
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
