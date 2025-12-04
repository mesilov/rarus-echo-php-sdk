<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\HttpClient;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Rarus\Echo\Exception\NetworkException;

/**
 * PSR-18 HTTP client implementation
 * Uses auto-discovered PSR-18 client via php-http/discovery
 */
final class PsrHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {
    }

    /**
     * @throws NetworkException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $request = $this->createRequest($method, $uri, $options);

        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException(
                sprintf('HTTP request failed: %s', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @throws NetworkException
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @throws NetworkException
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * Create PSR-7 request from options
     *
     * @param array<string,mixed> $options
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
                    throw new \InvalidArgumentException('Failed to encode body as JSON');
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
}
