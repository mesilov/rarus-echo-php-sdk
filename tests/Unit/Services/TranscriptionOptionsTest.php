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
        $options = TranscriptionOptions::default();

        $this->assertSame(TaskType::TRANSCRIPTION, $options->getTaskType());
        $this->assertSame(Language::AUTO, $options->getLanguage());
        $this->assertFalse($options->isCensor());
        $this->assertFalse($options->isSpeakersCorrection());
        $this->assertTrue($options->isStoreFile());
        $this->assertFalse($options->isLowPriority());
        $this->assertNull($options->getRequestSource());
    }

    public function testBuilderPattern(): void
    {
        $options = TranscriptionOptions::create()
            ->withTaskType(TaskType::DIARIZATION)
            ->withLanguage(Language::RU)
            ->withCensor()
            ->withSpeakersCorrection()
            ->withLowPriority()
            ->withRequestSource('test-source')
            ->build();

        $this->assertSame(TaskType::DIARIZATION, $options->getTaskType());
        $this->assertSame(Language::RU, $options->getLanguage());
        $this->assertTrue($options->isCensor());
        $this->assertTrue($options->isSpeakersCorrection());
        $this->assertTrue($options->isLowPriority());
        $this->assertSame('test-source', $options->getRequestSource());
    }

    public function testToHeaders(): void
    {
        $options = new TranscriptionOptions(
            taskType: TaskType::TIMESTAMPS,
            language: Language::EN,
            censor: true,
            speakersCorrection: false,
            storeFile: false,
            lowPriority: true
        );

        $headers = $options->toHeaders();

        $this->assertSame('timestamps', $headers['task-type']);
        $this->assertSame('en', $headers['language']);
        $this->assertSame('1', $headers['censor']);
        $this->assertSame('0', $headers['speakers-correction']);
        $this->assertSame('0', $headers['store-file']);
        $this->assertSame('1', $headers['low-priority']);
    }
}
