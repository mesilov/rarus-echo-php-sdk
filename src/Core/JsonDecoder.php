<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use Psr\Http\Message\ResponseInterface;

/**
 * JSON decoder for PSR-7 HTTP responses
 */
final class JsonDecoder
{
    /**
     * Decode JSON response body to array
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if JSON decoding fails
     */
    public static function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '' || $body === '0') {
            return [];
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode JSON response: ' . json_last_error_msg()
            );
        }

        return $data;
    }
}
