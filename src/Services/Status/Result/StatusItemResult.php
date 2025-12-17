<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Result;

use DateTimeImmutable;
use Rarus\Echo\Enum\TranscriptionStatus;

/**
 * Single status result item
 */
final readonly class StatusItemResult
{
    public function __construct(
        private string $fileId,
        private TranscriptionStatus $status,
        private int $fileSize,
        private int $fileDuration,
        private DateTimeImmutable $timestampArrival
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException   If required fields are missing
     * @throws \DateMalformedStringException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['file_id'])) {
            throw new \InvalidArgumentException('Missing required field: file_id');
        }

        if (!isset($data['status'])) {
            throw new \InvalidArgumentException('Missing required field: status');
        }

        if (!isset($data['timestamp_arrival'])) {
            throw new \InvalidArgumentException('Missing required field: timestamp_arrival');
        }

        return new self(
            fileId: $data['file_id'],
            status: TranscriptionStatus::from($data['status']),
            fileSize: (int) ($data['file_size'] ?? 0), // Optional, defaults to 0
            fileDuration: (int) ($data['file_duration'] ?? 0), // Optional, defaults to 0
            timestampArrival: new DateTimeImmutable($data['timestamp_arrival'])
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
