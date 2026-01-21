<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Queue\Result;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;

final class QueueInfoResultTest extends TestCase
{
    // Constructor Tests

    public function testConstructorCreatesObjectWithValidPositiveIntegers(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 5,
            filesSize: 150,
            filesDuration: 45
        );

        $this->assertSame(5, $queueInfo->filesCount);
        $this->assertSame(150, $queueInfo->filesSize);
        $this->assertSame(45, $queueInfo->filesDuration);
    }

    public function testConstructorCreatesObjectWithZeroValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 0,
            filesSize: 0,
            filesDuration: 0
        );

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    public function testConstructorCreatesObjectWithLargeNumbers(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 1000,
            filesSize: 50000,
            filesDuration: 100000
        );

        $this->assertSame(1000, $queueInfo->filesCount);
        $this->assertSame(50000, $queueInfo->filesSize);
        $this->assertSame(100000, $queueInfo->filesDuration);
    }

    // fromArray() Tests - Valid Data Cases

    public function testFromArrayCreatesObjectWithCompleteValidData(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 5,
                    'files_size' => 150,
                    'files_duration' => 45,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertInstanceOf(QueueInfoResult::class, $queueInfo);
        $this->assertSame(5, $queueInfo->filesCount);
        $this->assertSame(150, $queueInfo->filesSize);
        $this->assertSame(45, $queueInfo->filesDuration);
    }

    public function testFromArrayCreatesObjectWithZeroValues(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 0,
                    'files_size' => 0,
                    'files_duration' => 0,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    // fromArray() Tests - Missing/Invalid Data Cases

    public function testFromArrayDefaultsToZeroWhenResultsKeyMissing(): void
    {
        $data = [];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    public function testFromArrayDefaultsToZeroWhenResultsArrayEmpty(): void
    {
        $data = ['results' => []];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    public function testFromArrayDefaultsToZeroWhenFilesCountMissing(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_size' => 100,
                    'files_duration' => 30,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(100, $queueInfo->filesSize);
        $this->assertSame(30, $queueInfo->filesDuration);
    }

    public function testFromArrayDefaultsToZeroWhenFilesSizeMissing(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 5,
                    'files_duration' => 30,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(5, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(30, $queueInfo->filesDuration);
    }

    public function testFromArrayDefaultsToZeroWhenFilesDurationMissing(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 5,
                    'files_size' => 100,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(5, $queueInfo->filesCount);
        $this->assertSame(100, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    public function testFromArrayDefaultsToZeroWhenAllFieldsMissing(): void
    {
        $data = [
            'results' => [
                0 => [],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    public function testFromArrayHandlesNullValues(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => null,
                    'files_size' => null,
                    'files_duration' => null,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfo->filesCount);
        $this->assertSame(0, $queueInfo->filesSize);
        $this->assertSame(0, $queueInfo->filesDuration);
    }

    // fromArray() Tests - Type Coercion

    public function testFromArrayCoercesStringValuesToIntegers(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => '10',
                    'files_size' => '200',
                    'files_duration' => '60',
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(10, $queueInfo->filesCount);
        $this->assertSame(200, $queueInfo->filesSize);
        $this->assertSame(60, $queueInfo->filesDuration);
    }

    public function testFromArrayCoercesFloatValuesToIntegers(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 5.8,
                    'files_size' => 150.9,
                    'files_duration' => 45.2,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(5, $queueInfo->filesCount);
        $this->assertSame(150, $queueInfo->filesSize);
        $this->assertSame(45, $queueInfo->filesDuration);
    }

    // fromArray() Tests - Edge Cases

    public function testFromArrayHandlesNegativeValues(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => -5,
                    'files_size' => -100,
                    'files_duration' => -30,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(-5, $queueInfo->filesCount);
        $this->assertSame(-100, $queueInfo->filesSize);
        $this->assertSame(-30, $queueInfo->filesDuration);
    }

    public function testFromArrayHandlesVeryLargeValues(): void
    {
        $data = [
            'results' => [
                0 => [
                    'files_count' => 999999,
                    'files_size' => 9999999,
                    'files_duration' => 9999999,
                ],
            ],
        ];

        $queueInfo = QueueInfoResult::fromArray($data);

        $this->assertSame(999999, $queueInfo->filesCount);
        $this->assertSame(9999999, $queueInfo->filesSize);
        $this->assertSame(9999999, $queueInfo->filesDuration);
    }

    // isEmpty() Method Tests

    public function testIsEmptyReturnsTrueWhenFilesCountIsZero(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 0,
            filesSize: 100,
            filesDuration: 50
        );

        $this->assertTrue($queueInfo->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenFilesCountIsOne(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 1,
            filesSize: 0,
            filesDuration: 0
        );

        $this->assertFalse($queueInfo->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenFilesCountIsPositive(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 10,
            filesSize: 200,
            filesDuration: 75
        );

        $this->assertFalse($queueInfo->isEmpty());
    }

    public function testIsEmptyOnlyDependsOnFilesCount(): void
    {
        $queueInfoWithZeroCount = new QueueInfoResult(
            filesCount: 0,
            filesSize: 999,
            filesDuration: 888
        );

        $this->assertTrue($queueInfoWithZeroCount->isEmpty());
    }

    // toString() Method Tests

    public function testToStringFormatsWithStandardValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 5,
            filesSize: 150,
            filesDuration: 45
        );

        $expected = 'Queue: 5 files, 150 MB, 45 minutes';
        $this->assertSame($expected, $queueInfo->toString());
    }

    public function testToStringFormatsWithZeroValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 0,
            filesSize: 0,
            filesDuration: 0
        );

        $expected = 'Queue: 0 files, 0 MB, 0 minutes';
        $this->assertSame($expected, $queueInfo->toString());
    }

    public function testToStringFormatsWithLargeValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 1000,
            filesSize: 50000,
            filesDuration: 100000
        );

        $expected = 'Queue: 1000 files, 50000 MB, 100000 minutes';
        $this->assertSame($expected, $queueInfo->toString());
    }

    public function testToStringFormatsWithMixedValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 0,
            filesSize: 100,
            filesDuration: 50
        );

        $expected = 'Queue: 0 files, 100 MB, 50 minutes';
        $this->assertSame($expected, $queueInfo->toString());
    }

    public function testToStringFormatsWithSingleFile(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 1,
            filesSize: 25,
            filesDuration: 10
        );

        $expected = 'Queue: 1 files, 25 MB, 10 minutes';
        $this->assertSame($expected, $queueInfo->toString());
    }

    // Property Access Tests

    public function testFilesCountPropertyIsAccessible(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 7,
            filesSize: 200,
            filesDuration: 60
        );

        $this->assertSame(7, $queueInfo->filesCount);
        $this->assertIsInt($queueInfo->filesCount);
    }

    public function testFilesSizePropertyIsAccessible(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 7,
            filesSize: 200,
            filesDuration: 60
        );

        $this->assertSame(200, $queueInfo->filesSize);
        $this->assertIsInt($queueInfo->filesSize);
    }

    public function testFilesDurationPropertyIsAccessible(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 7,
            filesSize: 200,
            filesDuration: 60
        );

        $this->assertSame(60, $queueInfo->filesDuration);
        $this->assertIsInt($queueInfo->filesDuration);
    }

    public function testReadonlyPropertiesReturnConsistentValues(): void
    {
        $queueInfo = new QueueInfoResult(
            filesCount: 3,
            filesSize: 75,
            filesDuration: 25
        );

        $this->assertSame(3, $queueInfo->filesCount);
        $this->assertSame(3, $queueInfo->filesCount);

        $this->assertSame(75, $queueInfo->filesSize);
        $this->assertSame(75, $queueInfo->filesSize);

        $this->assertSame(25, $queueInfo->filesDuration);
        $this->assertSame(25, $queueInfo->filesDuration);
    }
}
