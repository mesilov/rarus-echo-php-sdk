<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;

/** @internal Explicit ownership of ordered prepared paths; no destructor cleanup. */
final class PreparedFileSet
{
    /** @var list<array{original: string, prepared: string}> */
    private array $associations = [];
    /** @var list<string> */
    private array $owned = [];
    private ?string $directory = null;

    public function __construct(private readonly ConfigFileProtection $protection = new ConfigFileProtection())
    {
    }

    public function add(string $original, string $prepared): void
    {
        $this->associations[] = ['original' => $original, 'prepared' => $prepared];
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_column($this->associations, 'prepared');
    }

    public function allocate(string $extension): string
    {
        if ($this->directory === null) {
            $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rarus-echo-media-' . bin2hex(random_bytes(16));
            if (!mkdir($this->directory, 0o700)) {
                throw new \RuntimeException('Cannot create private prepared-media directory.');
            }

            try {
                $this->protection->protect($this->directory);
            } catch (\Throwable $exception) {
                @rmdir($this->directory);
                $this->directory = null;

                throw $exception;
            }
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . 'prepared-' . count($this->owned) . '.' . $extension;
        $this->owned[] = $path;

        return $path;
    }

    public function checkBudget(): void
    {
        $total = 0;
        foreach ($this->owned as $path) {
            clearstatcache(true, $path);
            if (is_file($path)) {
                $size = filesize($path);
                if ($size === false || $size > 500 * 1024 * 1024) {
                    throw new \RuntimeException('Prepared audio exceeds the 500 MiB upload limit.');
                }
                $total += $size;
            }
        }
        // Reserve space for the bounded frame listing and subprocess diagnostics.
        if ($total > 1024 * 1024 * 1024 - 66 * 1024 * 1024) {
            throw new \RuntimeException('Prepared media exceeds the 1 GiB temporary-output budget.');
        }
        if ($this->directory !== null) {
            $available = disk_free_space($this->directory);
            if ($available === false || $available < 1024 * 1024) {
                throw new \RuntimeException('Insufficient available space for media preparation.');
            }
        }
    }

    public function cleanup(): void
    {
        foreach ($this->owned as $path) {
            for ($attempt = 0; $attempt < 5 && is_file($path); ++$attempt) {
                $removed = @unlink($path);
                clearstatcache(true, $path);
                if (!$removed) {
                    usleep(20000);
                }
            }
            if (is_file($path)) {
                throw new \RuntimeException('Cannot remove prepared audio after closing media/upload handles: ' . $path);
            }
        }
        if ($this->directory !== null && is_dir($this->directory) && !@rmdir($this->directory)) {
            throw new \RuntimeException('Cannot remove prepared media directory.');
        }
        $this->owned = [];
        $this->directory = null;
    }
}
