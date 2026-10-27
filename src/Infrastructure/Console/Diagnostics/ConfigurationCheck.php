<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;
use Rarus\Echo\Infrastructure\Console\Configuration\DotenvConfigurationException;
use Rarus\Echo\Infrastructure\Console\Configuration\ResolvedConnection;

/** @internal Validates the file independently of effective connection selection. */
final readonly class ConfigurationCheck
{
    public function __construct(
        private ConnectionConfigResolver $resolver,
        private ConfigPathResolver $paths,
        private ConnectionConfigStore $store = new ConnectionConfigStore()
    ) {
    }

    public function run(?string $explicit = null): ConfigurationDiagnosis
    {
        $connection = null;
        $resolutionError = null;

        try {
            // Dotenv can affect HOME/XDG_CONFIG_HOME/APPDATA as well as credentials.
            $connection = $this->resolver->resolveConnection($explicit);
        } catch (\Throwable $exception) {
            $resolutionError = $exception;
        }
        $details = ['source' => $explicit !== null ? 'explicit_config' : 'default_config'];
        $code = 'config_ok';
        $message = 'Configuration is valid.';

        try {
            $path = $this->paths->resolve($explicit);
            $details['path'] = $path;
            if (!file_exists($path) && !is_link($path)) {
                $code = 'missing_config';
                $message = 'Configuration file is missing. Run rarus-echo install or use --config PATH.';
            } else {
                $code = 'invalid_config';
                $message = 'Configuration is unreadable, unsafe or invalid. Check permissions, JSON version, api_key, user_id and base_url, or run rarus-echo install.';
                $this->store->load($path);
                $code = 'config_ok';
                $message = 'Configuration is valid.';
            }
        } catch (\Throwable) {
            if (!isset($details['path'])) {
                $code = 'config_path_unavailable';
                $message = 'Cannot locate configuration. Use --config PATH.';
            }
        }

        $details['file_status'] = $code;

        if (!$connection instanceof ResolvedConnection) {
            if ($resolutionError instanceof DotenvConfigurationException) {
                $details['source'] = 'environment';
                $code = 'invalid_connection';
                $message = 'Cannot load connection settings from .env. Correct its syntax or use --config PATH.';
            } elseif ($explicit === null && $this->hasEnvironmentCredentials()) {
                $details['source'] = 'environment';
                $code = 'invalid_connection';
                $message = 'Invalid environment connection settings. Set valid RARUS_ECHO_API_KEY, RARUS_ECHO_USER_ID and RARUS_ECHO_BASE_URL; values are not mixed with the config file.';
            } elseif ($code === 'config_ok') {
                $code = 'invalid_connection';
                $message = 'Cannot resolve connection settings. Correct the environment/.env or run rarus-echo install.';
            }

            return new ConfigurationDiagnosis(new CheckResult('error', $code, $message, $details));
        }

        $details['source'] = $connection->source;

        return new ConfigurationDiagnosis(new CheckResult($code === 'config_ok' ? 'ok' : 'warning', $code, $message, $details), $connection->credentials);
    }

    private function hasEnvironmentCredentials(): bool
    {
        return ($_ENV['RARUS_ECHO_API_KEY'] ?? $_SERVER['RARUS_ECHO_API_KEY'] ?? '') !== ''
            || ($_ENV['RARUS_ECHO_USER_ID'] ?? $_SERVER['RARUS_ECHO_USER_ID'] ?? '') !== '';
    }
}
