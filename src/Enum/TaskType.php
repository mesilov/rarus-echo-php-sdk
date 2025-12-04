<?php

declare(strict_types=1);

namespace Rarus\Echo\Enum;

/**
 * Task types for transcription
 */
enum TaskType: string
{
    /**
     * Standard transcription (default)
     * Suitable for most cases
     */
    case TRANSCRIPTION = 'transcription';

    /**
     * Transcription with timestamps
     * Includes time markers for each segment
     */
    case TIMESTAMPS = 'timestamps';

    /**
     * Transcription with diarization
     * Separates speech by different speakers
     */
    case DIARIZATION = 'diarization';

    /**
     * Raw transcription text
     * Unprocessed transcription output
     */
    case RAW_TRANSCRIPTION = 'raw_transcription';

    /**
     * Get all available task types
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get task type description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::TRANSCRIPTION => 'Standard transcription',
            self::TIMESTAMPS => 'Transcription with timestamps',
            self::DIARIZATION => 'Transcription with speaker diarization',
            self::RAW_TRANSCRIPTION => 'Raw transcription text',
        };
    }
}
