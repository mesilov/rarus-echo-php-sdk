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

        $result = TranscriptItemResult::fromArray($data);

        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result->getFileId());
        $this->assertSame(TaskType::TRANSCRIPTION, $result->getTaskType());
        $this->assertSame(TranscriptionStatus::SUCCESS, $result->getStatus());
        $this->assertSame('Test transcription result', $result->getResult());
    }

    public function testIsSuccessful(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'success', 'result' => ''];
        $result = TranscriptItemResult::fromArray($data);

        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->isFailed());
        $this->assertFalse($result->isInProgress());
    }

    public function testIsFailed(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'failure', 'result' => ''];
        $result = TranscriptItemResult::fromArray($data);

        $this->assertTrue($result->isFailed());
        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($result->isInProgress());
    }

    public function testIsInProgress(): void
    {
        $data = ['file_id' => 'test', 'task_type' => 'transcription', 'status' => 'waiting', 'result' => ''];
        $result = TranscriptItemResult::fromArray($data);

        $this->assertTrue($result->isInProgress());
        $this->assertFalse($result->isSuccessful());
        $this->assertFalse($result->isFailed());
    }
}
