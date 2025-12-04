<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\HttpClient;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Middleware to retry failed requests
 */
final class RetryMiddleware implements ClientInterface
{
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $maxRetries = self::DEFAULT_MAX_RETRIES,
        private readonly int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                $delay = $this->calculateBackoffDelay($attempt);

                $this->logger->warning(
                    'HTTP request failed, retrying',
                    [
                        'attempt' => $attempt,
                        'max_retries' => $this->maxRetries,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                        'uri' => (string) $request->getUri(),
                    ]
                );

                usleep($delay * 1000); // Convert ms to microseconds
            }
        }

        throw $lastException;
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        return $this->retryDelayMs * (2 ** ($attempt - 1));
    }
}
