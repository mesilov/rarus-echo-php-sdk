<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Request;

use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;

/**
 * Options for transcription request
 */
final readonly class TranscriptionOptions
{
    public function __construct(
        private TaskType $taskType = TaskType::TRANSCRIPTION,
        private Language $language = Language::AUTO,
        private bool $censor = false,
        private bool $speakersCorrection = false,
        private bool $storeFile = true,
        private bool $lowPriority = false,
        private ?string $requestSource = null
    ) {
    }

    /**
     * Create default options
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create options with fluent builder pattern
     */
    public static function create(): TranscriptionOptionsBuilder
    {
        return new TranscriptionOptionsBuilder();
    }

    public function getTaskType(): TaskType
    {
        return $this->taskType;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function isCensor(): bool
    {
        return $this->censor;
    }

    public function isSpeakersCorrection(): bool
    {
        return $this->speakersCorrection;
    }

    public function isStoreFile(): bool
    {
        return $this->storeFile;
    }

    public function isLowPriority(): bool
    {
        return $this->lowPriority;
    }

    public function getRequestSource(): ?string
    {
        return $this->requestSource;
    }

    /**
     * Convert options to HTTP headers
     *
     * @return array<string, string>
     */
    public function toHeaders(): array
    {
        $headers = [
            'task-type' => $this->taskType->value,
            'language' => $this->language->value,
            'censor' => $this->censor ? '1' : '0',
            'speakers-correction' => $this->speakersCorrection ? '1' : '0',
            'store-file' => $this->storeFile ? '1' : '0',
            'low-priority' => $this->lowPriority ? '1' : '0',
        ];

        if ($this->requestSource !== null) {
            $headers['request-source'] = $this->requestSource;
        }

        return $headers;
    }
}
