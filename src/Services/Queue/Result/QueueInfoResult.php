<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Queue\Result;

/**
 * Queue information result
 */
final class QueueInfoResult
{
    public function __construct(
        private readonly float $filesCount,
        private readonly float $filesSize,
        private readonly float $filesDuration
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $results = $data['results'][0] ?? [];

        return new self(
            filesCount: (float) ($results['files_count'] ?? 0),
            filesSize: (float) ($results['files_size'] ?? 0),
            filesDuration: (float) ($results['files_duration'] ?? 0)
        );
    }

    /**
     * Get number of files in queue
     */
    public function getFilesCount(): float
    {
        return $this->filesCount;
    }

    /**
     * Get total size of files in queue (in megabytes)
     */
    public function getFilesSize(): float
    {
        return $this->filesSize;
    }

    /**
     * Get total duration of files in queue (in minutes)
     */
    public function getFilesDuration(): float
    {
        return $this->filesDuration;
    }

    /**
     * Check if queue is empty
     */
    public function isEmpty(): bool
    {
        return $this->filesCount === 0.0;
    }

    /**
     * Format as human-readable string
     */
    public function toString(): string
    {
        return sprintf(
            'Queue: %d files, %.2f MB, %.2f minutes',
            (int) $this->filesCount,
            $this->filesSize,
            $this->filesDuration
        );
    }
}
