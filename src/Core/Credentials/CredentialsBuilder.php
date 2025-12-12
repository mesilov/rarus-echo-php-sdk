<?php

declare(strict_types=1);

namespace Rarus\Echo\Core\Credentials;

use InvalidArgumentException;

/**
 * Builder for creating Credentials with fluent interface
 */
final class CredentialsBuilder
{
    private const DEFAULT_BASE_URL = 'https://production-ai-ui-api.ai.rarus-cloud.ru';

    private ?string $apiKey = null;
    private ?string $userId = null;
    private string $baseUrl = self::DEFAULT_BASE_URL;

    public function __construct()
    {
    }

    /**
     * Set API key
     */
    public function withApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Set user ID (UUID)
     */
    public function withUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Set custom base URL
     */
    public function withBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Load credentials from environment variables
     */
    public function fromEnvironment(): self
    {
        $this->apiKey = $_ENV['RARUS_ECHO_API_KEY'] ?? $_SERVER['RARUS_ECHO_API_KEY'] ?? null;
        $this->userId = $_ENV['RARUS_ECHO_USER_ID'] ?? $_SERVER['RARUS_ECHO_USER_ID'] ?? null;
        $this->baseUrl = $_ENV['RARUS_ECHO_BASE_URL'] ?? $_SERVER['RARUS_ECHO_BASE_URL'] ?? self::DEFAULT_BASE_URL;

        return $this;
    }

    /**
     * Build Credentials instance
     *
     * @throws InvalidArgumentException if required parameters are missing
     */
    public function build(): Credentials
    {
        if ($this->apiKey === null) {
            throw new InvalidArgumentException('API key is required');
        }

        if ($this->userId === null) {
            throw new InvalidArgumentException('User ID is required');
        }

        return Credentials::create(
            $this->apiKey,
            $this->userId,
            $this->baseUrl
        );
    }
}
