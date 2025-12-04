<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Result;

use DateTimeImmutable;
use Rarus\Echo\Enum\TranscriptionStatus;

/**
 * Single status result item
 */
final class StatusItemResult
{
    public function __construct(
        private readonly string $fileId,
        private readonly TranscriptionStatus $status,
        private readonly float $fileSize,
        private readonly float $fileDuration,
        private readonly DateTimeImmutable $timestampArrival
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fileId: $data['file_id'] ?? '',
            status: TranscriptionStatus::from($data['status'] ?? 'waiting'),
            fileSize: (float) ($data['file_size'] ?? 0),
            fileDuration: (float) ($data['file_duration'] ?? 0),
            timestampArrival: new DateTimeImmutable($data['timestamp_arrival'] ?? 'now')
        );
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getStatus(): TranscriptionStatus
    {
        return $this->status;
    }

    /**
     * Get file size in megabytes
     */
    public function getFileSize(): float
    {
        return $this->fileSize;
    }

    /**
     * Get file duration in minutes
     */
    public function getFileDuration(): float
    {
        return $this->fileDuration;
    }

    public function getTimestampArrival(): DateTimeImmutable
    {
        return $this->timestampArrival;
    }

    /**
     * Check if processing is completed
     */
    public function isCompleted(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Check if transcription is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === TranscriptionStatus::SUCCESS;
    }
}
