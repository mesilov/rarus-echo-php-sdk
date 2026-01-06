<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Queue\Result;

/**
 * Queue information result
 */
final readonly class QueueInfoResult
{
    public function __construct(
        private int $filesCount,
        private int $filesSize,
        private int $filesDuration
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
            filesCount: (int) ($results['files_count'] ?? 0),
            filesSize: (int) ($results['files_size'] ?? 0),
            filesDuration: (int) ($results['files_duration'] ?? 0)
        );
    }

    /**
     * Get number of files in queue
     */
    public function getFilesCount(): int
    {
        return $this->filesCount;
    }

    /**
     * Get total size of files in queue (in megabytes)
     */
    public function getFilesSize(): int
    {
        return $this->filesSize;
    }

    /**
     * Get total duration of files in queue (in minutes)
     */
    public function getFilesDuration(): int
    {
        return $this->filesDuration;
    }

    /**
     * Check if queue is empty
     */
    public function isEmpty(): bool
    {
        return $this->filesCount === 0;
    }

    /**
     * Format as human-readable string
     */
    public function toString(): string
    {
        return sprintf(
            'Queue: %d files, %d MB, %d minutes',
            $this->filesCount,
            $this->filesSize,
            $this->filesDuration
        );
    }
}
