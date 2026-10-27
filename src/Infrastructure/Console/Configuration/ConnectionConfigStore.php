<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

final readonly class ConnectionConfigStore
{
    public function __construct(private ConfigFileProtection $protection = new ConfigFileProtection())
    {
    }

    public function load(string $path): ConnectionConfig
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Cannot read connection configuration. Run rarus-echo install or select --config PATH.');
        }
        $this->protection->check($path);
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read connection configuration.');
        }

        try {
            return ConnectionConfig::fromArray(json_decode($contents, true, flags: JSON_THROW_ON_ERROR));
        } catch (\JsonException|\InvalidArgumentException) {
            throw new \RuntimeException('Invalid connection configuration; check version, api_key, user_id and base_url.');
        }
    }

    public function save(string $path, ConnectionConfig $config, bool $overwrite = false): void
    {
        $this->directory(dirname($path));
        $parent = realpath(dirname($path));
        if ($parent === false) {
            throw new \RuntimeException('Cannot resolve configuration directory.');
        }
        $path = $parent . DIRECTORY_SEPARATOR . basename($path);
        if (is_link($path) || (file_exists($path) && (!is_file($path) || !$overwrite))) {
            throw new \RuntimeException('Configuration exists or is unsafe; replacement requires confirmation.');
        }
        $temporary = $parent . DIRECTORY_SEPARATOR . '.rarus-echo-' . bin2hex(random_bytes(12)) . '.tmp';
        $mask = umask(0o077);

        try {
            $stream = @fopen($temporary, 'x+b');
        } finally {
            umask($mask);
        }
        if ($stream === false) {
            throw new \RuntimeException('Cannot create private configuration file.');
        }

        try {
            $this->protection->protect($temporary);
            $json = json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($stream, $json) !== strlen($json) || !fflush($stream)) {
                throw new \RuntimeException('Cannot write connection configuration.');
            }
            fclose($stream);
            $stream = null;
            $this->commit($temporary, $path, $overwrite);
            $this->protection->check($path);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function commit(string $temporary, string $path, bool $overwrite): void
    {
        if (!$overwrite) {
            // A hard link publishes the complete file only if the destination is still absent.
            if (!@link($temporary, $path)) {
                throw new \RuntimeException('Cannot create configuration; the destination may already exist.');
            }

            return;
        }
        if (is_link($path) || !@rename($temporary, $path)) {
            throw new \RuntimeException('Cannot atomically replace connection configuration.');
        }
    }

    private function directory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (file_exists($path) || $path === dirname($path)) {
            throw new \RuntimeException('Cannot create configuration directory.');
        }
        $this->directory(dirname($path));
        if (!@mkdir($path, 0o700)) {
            if (!is_dir($path)) {
                throw new \RuntimeException('Cannot create configuration directory.');
            }

            return;
        }
        $this->protection->protect($path);
    }
}
