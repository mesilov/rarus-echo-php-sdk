<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

use InvalidArgumentException;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Single transcription result item
 */
final readonly class TranscriptItemResult
{
    public function __construct(
        public Uuid $fileId,
        public ?TaskType $taskType,
        public TranscriptionStatus $transcriptionStatus,
        public ?string $result
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException If required fields are missing
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('file_id', $data)) {
            throw new InvalidArgumentException('Missing required field: file_id');
        }

        if (!array_key_exists('task_type', $data)) {
            throw new InvalidArgumentException('Missing required field: task_type');
        }

        if (!array_key_exists('status', $data)) {
            throw new InvalidArgumentException('Missing required field: status');
        }

        // Handle empty task_type (occurs when file is still queued/processing)
        $taskTypeValue = !empty($data['task_type']) ? $data['task_type'] : null;

        return new self(
            fileId: Uuid::fromString($data['file_id']),
            taskType: TaskType::from($taskTypeValue),
            transcriptionStatus: TranscriptionStatus::from($data['status']),
            result: $data['result'] ?? null
        );
    }

    /**
     * Check if transcription is completed successfully
     */
    public function isSuccessful(): bool
    {
        return $this->transcriptionStatus === TranscriptionStatus::SUCCESS;
    }

    /**
     * Check if transcription failed
     */
    public function isFailed(): bool
    {
        return $this->transcriptionStatus === TranscriptionStatus::FAILURE;
    }

    /**
     * Check if transcription is in progress
     */
    public function isInProgress(): bool
    {
        return $this->transcriptionStatus->isInProgress();
    }
}
