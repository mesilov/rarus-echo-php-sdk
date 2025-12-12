<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Request;

use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;

/**
 * Builder for TranscriptionOptions
 */
final class TranscriptionOptionsBuilder
{
    private TaskType $taskType = TaskType::TRANSCRIPTION;
    private Language $language = Language::AUTO;
    private bool $censor = false;
    private bool $speakersCorrection = false;
    private bool $storeFile = true;
    private bool $lowPriority = false;
    private ?string $requestSource = null;

    public function withTaskType(TaskType $taskType): self
    {
        $this->taskType = $taskType;

        return $this;
    }

    public function withLanguage(Language $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function withCensor(bool $censor = true): self
    {
        $this->censor = $censor;

        return $this;
    }

    public function withSpeakersCorrection(bool $speakersCorrection = true): self
    {
        $this->speakersCorrection = $speakersCorrection;

        return $this;
    }

    public function withStoreFile(bool $storeFile = true): self
    {
        $this->storeFile = $storeFile;

        return $this;
    }

    public function withLowPriority(bool $lowPriority = true): self
    {
        $this->lowPriority = $lowPriority;

        return $this;
    }

    public function withRequestSource(string $requestSource): self
    {
        $this->requestSource = $requestSource;

        return $this;
    }

    public function build(): TranscriptionOptions
    {
        return new TranscriptionOptions(
            $this->taskType,
            $this->language,
            $this->censor,
            $this->speakersCorrection,
            $this->storeFile,
            $this->lowPriority,
            $this->requestSource
        );
    }
}
