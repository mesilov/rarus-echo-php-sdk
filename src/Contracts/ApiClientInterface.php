<?php

declare(strict_types=1);

namespace Rarus\Echo\Contracts;

use Psr\Http\Message\ResponseInterface;
use Rarus\Echo\Core\Credentials;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;

/**
 * Main API client for Rarus Echo service
 * Handles HTTP communication with the API
 */
interface ApiClientInterface
{
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
     */
    public function get(string $endpoint, array $query = [], array $headers = []): ResponseInterface;

    /**
     * Send POST request to API
     *
     * @param string $endpoint API endpoint (without base URL)
     * @param array<string, mixed> $body Request body
     * @param array<string, string> $headers Additional headers
     *
     * @throws NetworkException
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws ApiException
     */
    public function post(string $endpoint, array $body = [], array $headers = []): ResponseInterface;

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
    public function postMultipart(string $endpoint, array $files, array $headers = []): ResponseInterface;

    /**
     * Get credentials
     */
    public function getCredentials(): Credentials;
}
