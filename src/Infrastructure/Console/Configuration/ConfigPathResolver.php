<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Configuration;

final readonly class ConfigPathResolver
{
    /** @param array<string, string>|null $environment */
    public function __construct(
        private string $workingDirectory,
        private ?array $environment = null,
        private string $os = PHP_OS_FAMILY
    ) {
    }

    public function resolve(?string $explicit = null): string
    {
        if ($explicit !== null) {
            if ($explicit === '' || str_contains($explicit, "\0") || str_contains($explicit, '://')) {
                throw new \InvalidArgumentException('--config requires a local file path.');
            }

            return $this->absolute($explicit) ? $explicit : rtrim($this->workingDirectory, '/\\') . '/' . $explicit;
        }

        $environment = $this->environment ?? getenv();
        if ($this->os === 'Windows') {
            $base = $environment['APPDATA'] ?? '';
        } else {
            $xdg = $environment['XDG_CONFIG_HOME'] ?? '';
            $base = $this->absolute($xdg) ? $xdg : (($environment['HOME'] ?? '') === '' ? '' : rtrim($environment['HOME'], '/\\') . '/.config');
        }

        if (!$this->absolute($base)) {
            throw new \RuntimeException('No user configuration directory is available; use --config PATH.');
        }

        return rtrim(str_replace('\\', '/', $base), '/') . '/rarus-echo/config.json';
    }

    private function absolute(string $path): bool
    {
        if ($this->os !== 'Windows') {
            return str_starts_with($path, '/');
        }

        return preg_match('~^(?:[A-Za-z]:[\\\\/]|\\\\\\\\)~', $path) === 1;
    }
}
