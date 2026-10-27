<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

use Rarus\Echo\Core\Credentials;
use Symfony\Component\Uid\Uuid;

/** @internal One complete connection; values are never included in validation errors. */
final readonly class ConnectionConfig
{
    public const string DEFAULT_BASE_URL = 'https://production-ai-ui-api.ai.rarus-cloud.ru';

    private function __construct(private Credentials $credentials)
    {
    }

    public static function fromValues(string $apiKey, string $userId, string $baseUrl = self::DEFAULT_BASE_URL): self
    {
        self::validateUuid($apiKey, 'api_key');
        self::validateUuid($userId, 'user_id');
        self::validateUrl($baseUrl);

        return new self(Credentials::fromString($apiKey, $userId, $baseUrl));
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || ($data['version'] ?? null) !== 1
            || array_diff(array_keys($data), ['version', 'api_key', 'user_id', 'base_url']) !== []
            || !is_string($data['api_key'] ?? null) || !is_string($data['user_id'] ?? null)
            || !is_string($data['base_url'] ?? null)
        ) {
            throw new \InvalidArgumentException('Invalid connection configuration schema.');
        }

        return self::fromValues($data['api_key'], $data['user_id'], $data['base_url']);
    }

    public static function validateUuid(string $value, string $field): string
    {
        try {
            Uuid::fromString($value);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Invalid ' . $field . ': expected a UUID.');
        }

        return $value;
    }

    public static function validateUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        ) {
            throw new \InvalidArgumentException('Invalid base_url: expected an HTTP(S) URL without user information or fragment.');
        }

        return $url;
    }

    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    /** @return array{version: int, api_key: string, user_id: string, base_url: string} */
    public function toArray(): array
    {
        return [
            'version' => 1,
            'api_key' => $this->credentials->getApiKey()->toRfc4122(),
            'user_id' => $this->credentials->getUserId()->toRfc4122(),
            'base_url' => $this->credentials->getBaseUrl(),
        ];
    }
}
