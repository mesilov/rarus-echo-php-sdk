<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Request;

/**
 * Request for loading files from Rarus Drive
 */
final class DriveRequest
{
    public function __construct(
        private readonly string $targetPath = '/',
        private readonly bool $isImmediate = false
    ) {
    }

    /**
     * Create request for file path
     */
    public static function forPath(string $path, bool $highPriority = false): self
    {
        return new self($path, $highPriority);
    }

    /**
     * Create request for folder path
     */
    public static function forFolder(string $folderPath, bool $highPriority = false): self
    {
        return new self($folderPath, $highPriority);
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function isImmediate(): bool
    {
        return $this->isImmediate;
    }

    /**
     * Convert to request body
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_path' => $this->targetPath,
        ];
    }

    /**
     * Get headers for request
     *
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        return [
            'is-immediate' => $this->isImmediate ? '1' : '0',
        ];
    }
}
