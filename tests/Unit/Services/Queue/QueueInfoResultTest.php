<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Queue;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;

final class QueueInfoResultTest extends TestCase
{
    public function testFromArray(): void
    {
        $data = [
            'results' => [
                [
                    'files_count' => 15.0,
                    'files_size' => 250.0,
                    'files_duration' => 125.0,
                ],
            ],
        ];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertSame(15, $queueInfoResult->filesCount);
        $this->assertSame(250, $queueInfoResult->filesSize);
        $this->assertSame(125, $queueInfoResult->filesDuration);
    }

    public function testFromArrayWithDefaultValues(): void
    {
        $data = ['results' => [[]]];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfoResult->filesCount);
        $this->assertSame(0, $queueInfoResult->filesSize);
        $this->assertSame(0, $queueInfoResult->filesDuration);
    }

    public function testIsEmptyReturnsTrueWhenNoFiles(): void
    {
        $data = [
            'results' => [
                [
                    'files_count' => 0,
                    'files_size' => 0,
                    'files_duration' => 0,
                ],
            ],
        ];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertTrue($queueInfoResult->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasFiles(): void
    {
        $data = [
            'results' => [
                [
                    'files_count' => 1,
                    'files_size' => 10,
                    'files_duration' => 5,
                ],
            ],
        ];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertFalse($queueInfoResult->isEmpty());
    }

    public function testToString(): void
    {
        $data = [
            'results' => [
                [
                    'files_count' => 15,
                    'files_size' => 250,
                    'files_duration' => 125,
                ],
            ],
        ];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertSame('Queue: 15 files, 250 MB, 125 minutes', $queueInfoResult->toString());
    }

    public function testFromArrayWithMissingResultsKey(): void
    {
        $data = [];

        $queueInfoResult = QueueInfoResult::fromArray($data);

        $this->assertSame(0, $queueInfoResult->filesCount);
        $this->assertSame(0, $queueInfoResult->filesSize);
        $this->assertSame(0, $queueInfoResult->filesDuration);
    }
}
