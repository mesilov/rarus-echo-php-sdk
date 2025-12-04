<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\HttpClient;

use Psr\Http\Message\ResponseInterface;
use Rarus\Echo\Exception\NetworkException;

/**
 * Interface for HTTP client
 */
interface HttpClientInterface
{
    /**
     * Send HTTP request
     *
     * @param string              $method  HTTP method (GET, POST, etc.)
     * @param string              $uri     Request URI
     * @param array<string,mixed> $options Request options (headers, body, etc.)
     *
     * @throws NetworkException
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface;

    /**
     * Send GET request
     *
     * @param string              $uri     Request URI
     * @param array<string,mixed> $options Request options
     *
     * @throws NetworkException
     */
    public function get(string $uri, array $options = []): ResponseInterface;

    /**
     * Send POST request
     *
     * @param string              $uri     Request URI
     * @param array<string,mixed> $options Request options
     *
     * @throws NetworkException
     */
    public function post(string $uri, array $options = []): ResponseInterface;
}
