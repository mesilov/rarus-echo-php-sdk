<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Status\Result;

use DateTimeImmutable;
use Rarus\Echo\Enum\TranscriptionStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Single status result item
 */
final readonly class StatusItemResult
{
    public function __construct(
        public Uuid $fileId,
        public TranscriptionStatus $transcriptionStatus,
        public int $fileSize,
        public int $fileDuration,
        public DateTimeImmutable $timestampArrival
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
            fileId: Uuid::fromString($data['file_id']),
            transcriptionStatus: TranscriptionStatus::from($data['status']),
            fileSize: (int) ($data['file_size'] ?? 0), // Optional, defaults to 0
            fileDuration: (int) ($data['file_duration'] ?? 0), // Optional, defaults to 0
            timestampArrival: new DateTimeImmutable($data['timestamp_arrival'])
        );
    }

    /**
     * Check if processing is completed
     */
    public function isCompleted(): bool
    {
        return $this->transcriptionStatus->isFinal();
    }

    /**
     * Check if transcription is successful
     */
    public function isSuccessful(): bool
    {
        return $this->transcriptionStatus === TranscriptionStatus::SUCCESS;
    }
}
