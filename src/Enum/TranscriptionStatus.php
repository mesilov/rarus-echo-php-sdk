<?php

declare(strict_types=1);

namespace Rarus\Echo\Enum;

/**
 * Transcription status
 */
enum TranscriptionStatus: string
{
    /**
     * Task is waiting in queue
     */
    case WAITING = 'waiting';

    /**
     * Task is being processed
     */
    case PROCESSING = 'processing';

    /**
     * Task completed successfully
     */
    case SUCCESS = 'success';

    /**
     * Task failed with error
     */
    case FAILURE = 'failure';

    /**
     * Get all available statuses
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if status is final (success or failure)
     */
    public function isFinal(): bool
    {
        return $this === self::SUCCESS || $this === self::FAILURE;
    }

    /**
     * Check if status is in progress
     */
    public function isInProgress(): bool
    {
        return $this === self::WAITING || $this === self::PROCESSING;
    }

    /**
     * Get status description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::WAITING => 'Waiting in queue',
            self::PROCESSING => 'Processing',
            self::SUCCESS => 'Completed successfully',
            self::FAILURE => 'Failed with error',
        };
    }
}
