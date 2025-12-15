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
        private TranscriptionStatus $status,
        private string $result
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
            taskType: TaskType::from($data['task_type'] ?? 'transcription'),
            status: TranscriptionStatus::from($data['status'] ?? 'waiting'),
            result: $data['result'] ?? ''
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
        return $this->status;
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
        return $this->status === TranscriptionStatus::SUCCESS;
    }

    /**
     * Check if transcription failed
     */
    public function isFailed(): bool
    {
        return $this->status === TranscriptionStatus::FAILURE;
    }

    /**
     * Check if transcription is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status->isInProgress();
    }
}
