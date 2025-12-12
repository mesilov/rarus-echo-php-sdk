<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TaskType;

final class TaskTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = TaskType::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(TaskType::TRANSCRIPTION, $cases);
        $this->assertContains(TaskType::TIMESTAMPS, $cases);
        $this->assertContains(TaskType::DIARIZATION, $cases);
        $this->assertContains(TaskType::RAW_TRANSCRIPTION, $cases);
    }

    public function testValues(): void
    {
        $values = TaskType::values();

        $this->assertCount(4, $values);
        $this->assertContains('transcription', $values);
        $this->assertContains('timestamps', $values);
        $this->assertContains('diarization', $values);
        $this->assertContains('raw_transcription', $values);
    }

    public function testGetDescription(): void
    {
        $this->assertSame('Standard transcription', TaskType::TRANSCRIPTION->getDescription());
        $this->assertSame('Transcription with timestamps', TaskType::TIMESTAMPS->getDescription());
        $this->assertSame('Transcription with speaker diarization', TaskType::DIARIZATION->getDescription());
        $this->assertSame('Raw transcription text', TaskType::RAW_TRANSCRIPTION->getDescription());
    }

    public function testFromString(): void
    {
        $this->assertSame(TaskType::TRANSCRIPTION, TaskType::from('transcription'));
        $this->assertSame(TaskType::DIARIZATION, TaskType::from('diarization'));
    }
}
