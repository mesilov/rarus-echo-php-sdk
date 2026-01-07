<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;

/**
 * Single transcription result item
 */
final readonly class TranscriptItemResult
{
    public function __construct(
        private string $fileId,
        private TaskType $taskType,
        private TranscriptionStatus $transcriptionStatus,
        private string $result
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['file_id'])) {
            throw new \InvalidArgumentException('Missing required field: file_id');
        }

        if (!isset($data['task_type'])) {
            throw new \InvalidArgumentException('Missing required field: task_type');
        }

        if (!isset($data['status'])) {
            throw new \InvalidArgumentException('Missing required field: status');
        }

        // Handle empty task_type (occurs when file is still queued/processing)
        $taskTypeValue = !empty($data['task_type']) ? $data['task_type'] : 'transcription';

        return new self(
            fileId: $data['file_id'],
            taskType: TaskType::from($taskTypeValue),
            transcriptionStatus: TranscriptionStatus::from($data['status']),
            result: $data['result'] ?? '' // result is optional (empty if not yet completed)
        );
    }

    public function getFileId(): string
    {
        return $this->fileId;
    }

    public function getTaskType(): TaskType
    {
        return $this->taskType;
    }

    public function getStatus(): TranscriptionStatus
    {
        return $this->transcriptionStatus;
    }

    public function getResult(): string
    {
        return $this->result;
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
