<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Transcription\Result\TranscriptItemResult;

final class TranscriptItemResultTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'file_id' => '123e4567-e89b-12d3-a456-426614174000',
            'task_type' => 'transcription',
            'status' => 'success',
            'result' => 'Test transcription result',
        ];

        $transcriptItemResult = TranscriptItemResult::fromArray($data);

        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $transcriptItemResult->getFileId());
        $this->assertSame(TaskType::TRANSCRIPTION, $transcriptItemResult->getTaskType());
        $this->assertSame(TranscriptionStatus::SUCCESS, $transcriptItemResult->getStatus());
        $this->assertSame('Test transcription result', $transcriptItemResult->getResult());
    }

    public function testIsSuccessful(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'success', 'result' => ''];
        $transcriptItemResult = TranscriptItemResult::fromArray($data);

        $this->assertTrue($transcriptItemResult->isSuccessful());
        $this->assertFalse($transcriptItemResult->isFailed());
        $this->assertFalse($transcriptItemResult->isInProgress());
    }

    public function testIsFailed(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'failure', 'result' => ''];
        $transcriptItemResult = TranscriptItemResult::fromArray($data);

        $this->assertTrue($transcriptItemResult->isFailed());
        $this->assertFalse($transcriptItemResult->isSuccessful());
        $this->assertFalse($transcriptItemResult->isInProgress());
    }

    public function testIsInProgress(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'waiting', 'result' => ''];
        $transcriptItemResult = TranscriptItemResult::fromArray($data);

        $this->assertTrue($transcriptItemResult->isInProgress());
        $this->assertFalse($transcriptItemResult->isSuccessful());
        $this->assertFalse($transcriptItemResult->isFailed());
    }
}
