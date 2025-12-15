<?php

declare(strict_types=1);

namespace Rarus\Echo\Core\Credentials;

use InvalidArgumentException;

/**
 * Credentials for Rarus Echo API
 * Stores API key, user ID, and base URL
 */
readonly final class Credentials
{
    private const string DEFAULT_BASE_URL = 'https://production-ai-ui-api.ai.rarus-cloud.ru';

    private function __construct(
        private string $apiKey,
        private string $userId,
        private string $baseUrl
    ) {
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException('API key cannot be empty');
        }

        if (empty($this->userId)) {
            throw new InvalidArgumentException('User ID cannot be empty');
        }

        if (empty($this->baseUrl)) {
            throw new InvalidArgumentException('Base URL cannot be empty');
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Base URL must be a valid URL');
        }
    }

    /**
     * Create credentials with required parameters
     */
    public static function create(
        string $apiKey,
        string $userId,
        ?string $baseUrl = null
    ): self {
        return new self(
            $apiKey,
            $userId,
            $baseUrl ?? self::DEFAULT_BASE_URL
        );
    }

    /**
     * Create credentials from environment variables
     */
    public static function fromEnvironment(): self
    {
        $apiKey = $_ENV['RARUS_ECHO_API_KEY'] ?? $_SERVER['RARUS_ECHO_API_KEY'] ?? '';
        $userId = $_ENV['RARUS_ECHO_USER_ID'] ?? $_SERVER['RARUS_ECHO_USER_ID'] ?? '';
        $baseUrl = $_ENV['RARUS_ECHO_BASE_URL'] ?? $_SERVER['RARUS_ECHO_BASE_URL'] ?? self::DEFAULT_BASE_URL;

        if (empty($apiKey)) {
            throw new InvalidArgumentException('RARUS_ECHO_API_KEY environment variable is not set');
        }

        if (empty($userId)) {
            throw new InvalidArgumentException('RARUS_ECHO_USER_ID environment variable is not set');
        }

        return new self($apiKey, $userId, $baseUrl);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
