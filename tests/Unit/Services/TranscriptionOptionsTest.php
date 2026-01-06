<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

final class TranscriptionOptionsTest extends TestCase
{
    public function testDefaultOptions(): void
    {
        $transcriptionOptions = TranscriptionOptions::default();

        $this->assertSame(TaskType::TRANSCRIPTION, $transcriptionOptions->getTaskType());
        $this->assertSame(Language::AUTO, $transcriptionOptions->getLanguage());
        $this->assertFalse($transcriptionOptions->isCensor());
        $this->assertFalse($transcriptionOptions->isSpeakersCorrection());
        $this->assertTrue($transcriptionOptions->isStoreFile());
        $this->assertFalse($transcriptionOptions->isLowPriority());
        $this->assertNull($transcriptionOptions->getRequestSource());
    }

    public function testBuilderPattern(): void
    {
        $transcriptionOptions = TranscriptionOptions::create()
            ->withTaskType(TaskType::DIARIZATION)
            ->withLanguage(Language::RU)
            ->withCensor()
            ->withSpeakersCorrection()
            ->withLowPriority()
            ->withRequestSource('test-source')
            ->build();

        $this->assertSame(TaskType::DIARIZATION, $transcriptionOptions->getTaskType());
        $this->assertSame(Language::RU, $transcriptionOptions->getLanguage());
        $this->assertTrue($transcriptionOptions->isCensor());
        $this->assertTrue($transcriptionOptions->isSpeakersCorrection());
        $this->assertTrue($transcriptionOptions->isLowPriority());
        $this->assertSame('test-source', $transcriptionOptions->getRequestSource());
    }

    public function testToHeaders(): void
    {
        $transcriptionOptions = new TranscriptionOptions(
            taskType: TaskType::TIMESTAMPS,
            language: Language::EN,
            censor: true,
            speakersCorrection: false,
            storeFile: false,
            lowPriority: true
        );

        $headers = $transcriptionOptions->toHeaders();

        $this->assertSame('timestamps', $headers['task-type']);
        $this->assertSame('en', $headers['language']);
        $this->assertSame('1', $headers['censor']);
        $this->assertSame('0', $headers['speakers-correction']);
        $this->assertSame('0', $headers['store-file']);
        $this->assertSame('1', $headers['low-priority']);
    }
}
