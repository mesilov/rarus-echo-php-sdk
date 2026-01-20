<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Status\Result;

use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Symfony\Component\Uid\Exception\InvalidArgumentException as UuidInvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use ValueError;

final class StatusItemResultTest extends TestCase
{
    private const string VALID_UUID = '12345678-1234-1234-1234-123456789abc';
    private const string VALID_TIMESTAMP = '2024-01-15T10:30:00+00:00';

    // Constructor Tests

    public function testConstructorCreatesObjectWithValidParameters(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $status = TranscriptionStatus::SUCCESS;
        $fileSize = 150;
        $fileDuration = 45;
        $timestamp = new DateTimeImmutable(self::VALID_TIMESTAMP);

        $result = new StatusItemResult(
            fileId: $uuid,
            transcriptionStatus: $status,
            fileSize: $fileSize,
            fileDuration: $fileDuration,
            timestampArrival: $timestamp
        );

        $this->assertSame($uuid, $result->fileId);
        $this->assertSame($status, $result->transcriptionStatus);
        $this->assertSame($fileSize, $result->fileSize);
        $this->assertSame($fileDuration, $result->fileDuration);
        $this->assertSame($timestamp, $result->timestampArrival);
    }

    public function testConstructorCreatesObjectWithZeroValues(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $result = new StatusItemResult(
            fileId: $uuid,
            transcriptionStatus: TranscriptionStatus::WAITING,
            fileSize: 0,
            fileDuration: 0,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame(0, $result->fileSize);
        $this->assertSame(0, $result->fileDuration);
    }

    public function testConstructorCreatesObjectWithLargeValues(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $result = new StatusItemResult(
            fileId: $uuid,
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            fileSize: 999999,
            fileDuration: 999999,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame(999999, $result->fileSize);
        $this->assertSame(999999, $result->fileDuration);
    }

    // fromArray() Tests - Valid Data Cases

    public function testFromArrayCreatesObjectWithCompleteValidData(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'file_size' => 150,
            'file_duration' => 45,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertInstanceOf(StatusItemResult::class, $result);
        $this->assertSame(self::VALID_UUID, $result->fileId->toRfc4122());
        $this->assertSame(TranscriptionStatus::SUCCESS, $result->transcriptionStatus);
        $this->assertSame(150, $result->fileSize);
        $this->assertSame(45, $result->fileDuration);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->timestampArrival);
    }

    public function testFromArrayCreatesObjectWithZeroValues(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'file_size' => 0,
            'file_duration' => 0,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(0, $result->fileSize);
        $this->assertSame(0, $result->fileDuration);
    }

    public function testFromArrayHandlesWaitingStatus(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(TranscriptionStatus::WAITING, $result->transcriptionStatus);
    }

    public function testFromArrayHandlesProcessingStatus(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'processing',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(TranscriptionStatus::PROCESSING, $result->transcriptionStatus);
    }

    public function testFromArrayHandlesSuccessStatus(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(TranscriptionStatus::SUCCESS, $result->transcriptionStatus);
    }

    public function testFromArrayHandlesFailureStatus(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'failure',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(TranscriptionStatus::FAILURE, $result->transcriptionStatus);
    }

    public function testFromArrayHandlesLargeValues(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'file_size' => 999999,
            'file_duration' => 999999,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(999999, $result->fileSize);
        $this->assertSame(999999, $result->fileDuration);
    }

    // fromArray() Tests - Missing Required Fields

    public function testFromArrayThrowsExceptionWhenFileIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: file_id');

        StatusItemResult::fromArray([
            'status' => 'success',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ]);
    }

    public function testFromArrayThrowsExceptionWhenStatusMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: status');

        StatusItemResult::fromArray([
            'file_id' => self::VALID_UUID,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ]);
    }

    public function testFromArrayThrowsExceptionWhenTimestampArrivalMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: timestamp_arrival');

        StatusItemResult::fromArray([
            'file_id' => self::VALID_UUID,
            'status' => 'success',
        ]);
    }

    // fromArray() Tests - Invalid Data

    public function testFromArrayThrowsExceptionForInvalidUuid(): void
    {
        $this->expectException(UuidInvalidArgumentException::class);

        StatusItemResult::fromArray([
            'file_id' => 'invalid-uuid-format',
            'status' => 'success',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ]);
    }

    public function testFromArrayThrowsExceptionForEmptyStringUuid(): void
    {
        $this->expectException(UuidInvalidArgumentException::class);

        StatusItemResult::fromArray([
            'file_id' => '',
            'status' => 'success',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ]);
    }

    public function testFromArrayThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(ValueError::class);

        StatusItemResult::fromArray([
            'file_id' => self::VALID_UUID,
            'status' => 'invalid_status',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ]);
    }

    public function testFromArrayThrowsExceptionForMalformedTimestamp(): void
    {
        $this->expectException(DateMalformedStringException::class);

        StatusItemResult::fromArray([
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'timestamp_arrival' => 'not-a-valid-timestamp',
        ]);
    }

    // fromArray() Tests - Optional Fields with Defaults

    public function testFromArrayDefaultsFileSizeToZeroWhenMissing(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'file_duration' => 30,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(0, $result->fileSize);
    }

    public function testFromArrayDefaultsFileDurationToZeroWhenMissing(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'file_size' => 100,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(0, $result->fileDuration);
    }

    public function testFromArrayDefaultsFileSizeToZeroWhenNull(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'file_size' => null,
            'file_duration' => 30,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(0, $result->fileSize);
    }

    public function testFromArrayDefaultsFileDurationToZeroWhenNull(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'waiting',
            'file_size' => 100,
            'file_duration' => null,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(0, $result->fileDuration);
    }

    // fromArray() Tests - Type Coercion

    public function testFromArrayCoercesStringNumbersToIntegers(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'file_size' => '150',
            'file_duration' => '45',
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(150, $result->fileSize);
        $this->assertSame(45, $result->fileDuration);
        $this->assertIsInt($result->fileSize);
        $this->assertIsInt($result->fileDuration);
    }

    public function testFromArrayTruncatesFloatsToIntegers(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'file_size' => 150.9,
            'file_duration' => 45.7,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(150, $result->fileSize);
        $this->assertSame(45, $result->fileDuration);
    }

    public function testFromArrayHandlesNegativeValues(): void
    {
        $data = [
            'file_id' => self::VALID_UUID,
            'status' => 'success',
            'file_size' => -50,
            'file_duration' => -30,
            'timestamp_arrival' => self::VALID_TIMESTAMP,
        ];

        $result = StatusItemResult::fromArray($data);

        $this->assertSame(-50, $result->fileSize);
        $this->assertSame(-30, $result->fileDuration);
    }

    // isCompleted() Method Tests

    public function testIsCompletedReturnsTrueWhenStatusIsSuccess(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertTrue($result->isCompleted());
    }

    public function testIsCompletedReturnsTrueWhenStatusIsFailure(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::FAILURE,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertTrue($result->isCompleted());
    }

    public function testIsCompletedReturnsFalseWhenStatusIsWaiting(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::WAITING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertFalse($result->isCompleted());
    }

    public function testIsCompletedReturnsFalseWhenStatusIsProcessing(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertFalse($result->isCompleted());
    }

    // isSuccessful() Method Tests

    public function testIsSuccessfulReturnsTrueOnlyWhenStatusIsSuccess(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseWhenStatusIsWaiting(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::WAITING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertFalse($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseWhenStatusIsProcessing(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertFalse($result->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseWhenStatusIsFailure(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::FAILURE,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertFalse($result->isSuccessful());
    }

    // Property Access Tests

    public function testFileIdPropertyIsAccessibleAndReturnsUuid(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $result = new StatusItemResult(
            fileId: $uuid,
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame($uuid, $result->fileId);
        $this->assertInstanceOf(Uuid::class, $result->fileId);
    }

    public function testTranscriptionStatusPropertyIsAccessible(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame(TranscriptionStatus::PROCESSING, $result->transcriptionStatus);
        $this->assertInstanceOf(TranscriptionStatus::class, $result->transcriptionStatus);
    }

    public function testFileSizePropertyIsAccessibleAndReturnsInt(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 200,
            fileDuration: 30,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame(200, $result->fileSize);
        $this->assertIsInt($result->fileSize);
    }

    public function testFileDurationPropertyIsAccessibleAndReturnsInt(): void
    {
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 60,
            timestampArrival: new DateTimeImmutable(self::VALID_TIMESTAMP)
        );

        $this->assertSame(60, $result->fileDuration);
        $this->assertIsInt($result->fileDuration);
    }

    public function testTimestampArrivalPropertyIsAccessible(): void
    {
        $timestamp = new DateTimeImmutable(self::VALID_TIMESTAMP);
        $result = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: $timestamp
        );

        $this->assertSame($timestamp, $result->timestampArrival);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->timestampArrival);
    }

    public function testReadonlyPropertiesReturnConsistentValues(): void
    {
        $uuid = Uuid::fromString(self::VALID_UUID);
        $status = TranscriptionStatus::SUCCESS;
        $timestamp = new DateTimeImmutable(self::VALID_TIMESTAMP);

        $result = new StatusItemResult(
            fileId: $uuid,
            transcriptionStatus: $status,
            fileSize: 150,
            fileDuration: 45,
            timestampArrival: $timestamp
        );

        $this->assertSame($uuid, $result->fileId);
        $this->assertSame($uuid, $result->fileId);

        $this->assertSame($status, $result->transcriptionStatus);
        $this->assertSame($status, $result->transcriptionStatus);

        $this->assertSame(150, $result->fileSize);
        $this->assertSame(150, $result->fileSize);

        $this->assertSame(45, $result->fileDuration);
        $this->assertSame(45, $result->fileDuration);

        $this->assertSame($timestamp, $result->timestampArrival);
        $this->assertSame($timestamp, $result->timestampArrival);
    }
}
