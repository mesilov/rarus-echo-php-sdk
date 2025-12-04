<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\HttpClient;

use Psr\Http\Message\RequestInterface;

/**
 * Middleware to add authorization header to requests
 */
final class AuthMiddleware
{
    public function __construct(
        private readonly string $apiKey
    ) {
    }

    /**
     * Add authorization header to request
     */
    public function __invoke(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', $this->apiKey);
    }
}
