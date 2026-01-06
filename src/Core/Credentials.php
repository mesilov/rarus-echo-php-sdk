<?php

declare(strict_types=1);

namespace Rarus\Echo\Core;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * Credentials for Rarus Echo API
 * Stores API key, user ID, and base URL
 */
final readonly class Credentials
{
    private const string DEFAULT_BASE_URL = 'https://production-ai-ui-api.ai.rarus-cloud.ru';

    private function __construct(
        private Uuid $apiKey,
        private Uuid $userId,
        private string $baseUrl
    ) {
        if ($this->baseUrl === '' || $this->baseUrl === '0') {
            throw new InvalidArgumentException('Base URL cannot be empty');
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Base URL must be a valid URL');
        }
    }

    /**
     * Create credentials from string parameters
     */
    public static function fromString(
        string $apiKey,
        string $userId,
        ?string $baseUrl = null
    ): self {
        try {
            $apiKeyUuid = Uuid::fromString($apiKey);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('API key is not a valid UUID: %s', $apiKey),
                0,
                $e
            );
        }

        try {
            $userIdUuid = Uuid::fromString($userId);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('User ID is not a valid UUID: %s', $userId),
                0,
                $e
            );
        }

        return new self(
            $apiKeyUuid,
            $userIdUuid,
            $baseUrl ?? self::DEFAULT_BASE_URL
        );
    }

    /**
     * Create credentials from environment variables
     */
    public static function fromEnvironment(): self
    {
        $apiKeyString = $_ENV['RARUS_ECHO_API_KEY'] ?? $_SERVER['RARUS_ECHO_API_KEY'] ?? '';
        $userIdString = $_ENV['RARUS_ECHO_USER_ID'] ?? $_SERVER['RARUS_ECHO_USER_ID'] ?? '';
        $baseUrl = $_ENV['RARUS_ECHO_BASE_URL'] ?? $_SERVER['RARUS_ECHO_BASE_URL'] ?? self::DEFAULT_BASE_URL;

        if (empty($apiKeyString)) {
            throw new InvalidArgumentException('RARUS_ECHO_API_KEY environment variable is not set');
        }

        if (empty($userIdString)) {
            throw new InvalidArgumentException('RARUS_ECHO_USER_ID environment variable is not set');
        }

        try {
            $apiKey = Uuid::fromString($apiKeyString);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('RARUS_ECHO_API_KEY is not a valid UUID: %s', $apiKeyString),
                0,
                $e
            );
        }

        try {
            $userId = Uuid::fromString($userIdString);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                sprintf('RARUS_ECHO_USER_ID is not a valid UUID: %s', $userIdString),
                0,
                $e
            );
        }

        return new self($apiKey, $userId, $baseUrl);
    }

    public function getApiKey(): Uuid
    {
        return $this->apiKey;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
