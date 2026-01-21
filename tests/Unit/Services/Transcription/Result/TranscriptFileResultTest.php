<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Transcription\Result;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Symfony\Component\Uid\Exception\InvalidArgumentException as UuidInvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class TranscriptFileResultTest extends TestCase
{
    private const string VALID_UUID = '11111111-1111-1111-1111-111111111111';

    public function testConstructorCreatesObjectWithAllParameters(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $status = TranscriptionStatus::SUCCESS;
        $taskType = TaskType::TRANSCRIPTION;
        $result = 'Transcription result text';

        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: $uuid,
            transcriptionStatus: $status,
            taskType: $taskType,
            result: $result
        );

        $this->assertSame($uuid, $fileItemTranscriptResult->fileId);
        $this->assertSame($status, $fileItemTranscriptResult->transcriptionStatus);
        $this->assertSame($taskType, $fileItemTranscriptResult->taskType);
        $this->assertSame($result, $fileItemTranscriptResult->result);
    }

    public function testFromArrayCreatesObjectWithValidData(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'task_type' => 'transcription',
            'status' => 'success',
            'result' => 'Sample transcription',
        ];

        $fileItemTranscriptResult = FileItemTranscriptResult::fromArray($data);

        $this->assertSame(self::VALID_UUID, $fileItemTranscriptResult->fileId->toRfc4122());
        $this->assertSame(TranscriptionStatus::SUCCESS, $fileItemTranscriptResult->transcriptionStatus);
        $this->assertSame(TaskType::TRANSCRIPTION, $fileItemTranscriptResult->taskType);
        $this->assertSame('Sample transcription', $fileItemTranscriptResult->result);
    }

    public function testFromArrayHandlesOptionalResultField(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'task_type' => 'transcription',
            'status' => 'processing',
        ];

        $fileItemTranscriptResult = FileItemTranscriptResult::fromArray($data);

        $this->assertNull($fileItemTranscriptResult->result);
    }

    public function testFromArrayHandlesEmptyTaskType(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'task_type' => '',
            'status' => 'waiting',
        ];

        $fileItemTranscriptResult = FileItemTranscriptResult::fromArray($data);

        $this->assertNull($fileItemTranscriptResult->taskType);
    }

    public function testFromArrayHandlesNullTaskType(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'task_type' => null,
            'status' => 'waiting',
        ];

        $fileItemTranscriptResult = FileItemTranscriptResult::fromArray($data);

        $this->assertNull($fileItemTranscriptResult->taskType);
    }

    public function testFromArrayThrowsExceptionWhenFileIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: file_id');

        FileItemTranscriptResult::fromArray([
            'task_type' => 'transcription',
            'status' => 'success',
        ]);
    }

    public function testFromArrayThrowsExceptionWhenTaskTypeMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: task_type');

        FileItemTranscriptResult::fromArray([
            'file_id' => self::VALID_UUID,
            'status' => 'success',
        ]);
    }

    public function testFromArrayThrowsExceptionWhenStatusMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        FileItemTranscriptResult::fromArray([
            'file_id' => self::VALID_UUID,
            'task_type' => 'transcription',
        ]);
    }

    public function testFromArrayThrowsExceptionForInvalidFileId(): void
    {
        $this->expectException(UuidInvalidArgumentException::class);

        FileItemTranscriptResult::fromArray([
            'file_id' => 'invalid-uuid',
            'task_type' => 'transcription',
            'status' => 'success',
        ]);
    }

    public function testFromArrayThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(ValueError::class);

        FileItemTranscriptResult::fromArray([
            'file_id' => self::VALID_UUID,
            'task_type' => 'transcription',
            'status' => 'invalid_status',
        ]);
    }

    public function testFromArrayThrowsExceptionForInvalidTaskType(): void
    {
        $this->expectException(ValueError::class);

        FileItemTranscriptResult::fromArray([
            'file_id' => self::VALID_UUID,
            'task_type' => 'invalid_task_type',
            'status' => 'success',
        ]);
    }

    public function testIsSuccessfulReturnsTrueWhenStatusIsSuccess(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            taskType: TaskType::TRANSCRIPTION,
            result: 'Transcription text'
        );

        $this->assertTrue($fileItemTranscriptResult->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseWhenStatusIsNotSuccess(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $taskType = TaskType::TRANSCRIPTION;
        $result = 'Transcription text';

        $waiting = new FileItemTranscriptResult($uuid, TranscriptionStatus::WAITING, $taskType, $result);
        $processing = new FileItemTranscriptResult($uuid, TranscriptionStatus::PROCESSING, $taskType, $result);
        $failure = new FileItemTranscriptResult($uuid, TranscriptionStatus::FAILURE, $taskType, $result);

        $this->assertFalse($waiting->isSuccessful());
        $this->assertFalse($processing->isSuccessful());
        $this->assertFalse($failure->isSuccessful());
    }

    public function testIsFailedReturnsTrueWhenStatusIsFailure(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::FAILURE,
            taskType: null,
            result: 'Error message'
        );

        $this->assertTrue($fileItemTranscriptResult->isFailed());
    }

    public function testIsFailedReturnsFalseWhenStatusIsNotFailure(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $taskType = TaskType::TRANSCRIPTION;
        $resultText = 'Transcription text';

        $waiting = new FileItemTranscriptResult($uuid, TranscriptionStatus::WAITING, $taskType, $resultText);
        $processing = new FileItemTranscriptResult($uuid, TranscriptionStatus::PROCESSING, $taskType, $resultText);
        $success = new FileItemTranscriptResult($uuid, TranscriptionStatus::SUCCESS, $taskType, $resultText);

        $this->assertFalse($waiting->isFailed());
        $this->assertFalse($processing->isFailed());
        $this->assertFalse($success->isFailed());
    }

    public function testIsInProgressReturnsTrueWhenStatusIsWaiting(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::WAITING,
            taskType: null,
            result: null
        );

        $this->assertTrue($fileItemTranscriptResult->isInProgress());
    }

    public function testIsInProgressReturnsTrueWhenStatusIsProcessing(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            taskType: TaskType::TRANSCRIPTION,
            result: null
        );

        $this->assertTrue($fileItemTranscriptResult->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenStatusIsSuccess(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            taskType: TaskType::TRANSCRIPTION,
            result: 'Completed transcription'
        );

        $this->assertFalse($fileItemTranscriptResult->isInProgress());
    }

    public function testIsInProgressReturnsFalseWhenStatusIsFailure(): void
    {
        $fileItemTranscriptResult = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::FAILURE,
            taskType: null,
            result: 'Error occurred'
        );

        $this->assertFalse($fileItemTranscriptResult->isInProgress());
    }
}
