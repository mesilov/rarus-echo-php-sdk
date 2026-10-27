<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

use Rarus\Echo\Core\Credentials;
use Symfony\Component\Dotenv\Dotenv;

final readonly class ConnectionConfigResolver
{
    public function __construct(
        private string $workingDirectory,
        private ConnectionConfigStore $store = new ConnectionConfigStore(),
        private ?ConfigPathResolver $paths = null
    ) {
    }

    public function resolve(?string $explicit = null): Credentials
    {
        $paths = $this->paths ?? new ConfigPathResolver($this->workingDirectory);
        if ($explicit !== null) {
            return $this->store->load($paths->resolve($explicit))->credentials();
        }
        if (is_file($this->workingDirectory . '/.env')) {
            try {
                (new Dotenv())->usePutenv()->loadEnv($this->workingDirectory . '/.env');
            } catch (\Throwable) {
                throw new \RuntimeException('Cannot load connection settings from .env.');
            }
        }

        $apiKey = $_ENV['RARUS_ECHO_API_KEY'] ?? $_SERVER['RARUS_ECHO_API_KEY'] ?? '';
        $userId = $_ENV['RARUS_ECHO_USER_ID'] ?? $_SERVER['RARUS_ECHO_USER_ID'] ?? '';
        if ($apiKey !== '' || $userId !== '') {
            if (!is_string($apiKey) || $apiKey === '') {
                throw new \InvalidArgumentException('RARUS_ECHO_API_KEY environment variable is not set.');
            }
            if (!is_string($userId) || $userId === '') {
                throw new \InvalidArgumentException('RARUS_ECHO_USER_ID environment variable is not set.');
            }
            $baseUrl = $_ENV['RARUS_ECHO_BASE_URL'] ?? $_SERVER['RARUS_ECHO_BASE_URL'] ?? ConnectionConfig::DEFAULT_BASE_URL;
            if (!is_string($baseUrl)) {
                throw new \InvalidArgumentException('Invalid RARUS_ECHO_BASE_URL.');
            }

            return ConnectionConfig::fromValues($apiKey, $userId, $baseUrl)->credentials();
        }

        $path = $paths->resolve();
        if (!file_exists($path) && !is_link($path)) {
            throw new \InvalidArgumentException('RARUS_ECHO_API_KEY environment variable is not set and no saved connection exists. Run rarus-echo install or use --config PATH.');
        }

        return $this->store->load($path)->credentials();
    }
}
