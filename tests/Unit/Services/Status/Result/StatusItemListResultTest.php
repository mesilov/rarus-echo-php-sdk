<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Status\Result;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Status\Result\StatusItemListResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Symfony\Component\Uid\Uuid;

final class StatusItemListResultTest extends TestCase
{
    private const string VALID_UUID_1 = '12345678-1234-1234-1234-123456789abc';
    private const string VALID_UUID_2 = '87654321-4321-4321-4321-cba987654321';
    private const string VALID_UUID_3 = 'abcdef00-1111-2222-3333-444444444444';
    private const string VALID_TIMESTAMP = '2024-01-15T10:30:00+00:00';

    // Constructor Tests

    public function testConstructorCreatesObjectWithEmptyResults(): void
    {
        $pagination = new Pagination(page: 1, perPage: 10, total: 0);
        $result = new StatusItemListResult([], $pagination);

        $this->assertSame([], $result->getResults());
        $this->assertInstanceOf(Pagination::class, $result->pagination);
        $this->assertSame(1, $result->pagination->page);
        $this->assertSame(10, $result->pagination->perPage);
    }

    public function testConstructorCreatesObjectWithOneResult(): void
    {
        $statusItem = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID_1),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new \DateTimeImmutable(self::VALID_TIMESTAMP)
        );
        $pagination = new Pagination(page: 1, perPage: 10, total: 1);

        $result = new StatusItemListResult([$statusItem], $pagination);

        $this->assertCount(1, $result->getResults());
        $this->assertInstanceOf(StatusItemResult::class, $result->getResults()[0]);
        $this->assertSame($statusItem, $result->getResults()[0]);
    }

    public function testConstructorCreatesObjectWithMultipleResults(): void
    {
        $statusItem1 = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID_1),
            transcriptionStatus: TranscriptionStatus::WAITING,
            fileSize: 100,
            fileDuration: 30,
            timestampArrival: new \DateTimeImmutable(self::VALID_TIMESTAMP)
        );
        $statusItem2 = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID_2),
            transcriptionStatus: TranscriptionStatus::PROCESSING,
            fileSize: 200,
            fileDuration: 60,
            timestampArrival: new \DateTimeImmutable(self::VALID_TIMESTAMP)
        );
        $statusItem3 = new StatusItemResult(
            fileId: Uuid::fromString(self::VALID_UUID_3),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 150,
            fileDuration: 45,
            timestampArrival: new \DateTimeImmutable(self::VALID_TIMESTAMP)
        );
        $pagination = new Pagination(page: 1, perPage: 10, total: 1);

        $result = new StatusItemListResult([$statusItem1, $statusItem2, $statusItem3], $pagination);

        $this->assertCount(3, $result->getResults());
        $this->assertContainsOnlyInstancesOf(StatusItemResult::class, $result->getResults());
        $this->assertSame($statusItem1, $result->getResults()[0]);
        $this->assertSame($statusItem2, $result->getResults()[1]);
        $this->assertSame($statusItem3, $result->getResults()[2]);
    }

    // fromArray() - Valid Data Tests

    public function testFromArrayCreatesObjectWithEmptyResults(): void
    {
        $data = [
            'results' => [],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 0,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertSame([], $result->getResults());
        $this->assertSame(1, $result->pagination->page);
        $this->assertSame(10, $result->pagination->perPage);
        $this->assertSame(0, $result->pagination->total);
    }

    public function testFromArrayCreatesObjectWithOneResult(): void
    {
        $data = [
            'results' => [
                [
                    'file_id' => self::VALID_UUID_1,
                    'status' => 'success',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertCount(1, $result->getResults());
        $this->assertInstanceOf(StatusItemResult::class, $result->getResults()[0]);
        $this->assertSame(self::VALID_UUID_1, $result->getResults()[0]->fileId->toRfc4122());
        $this->assertSame(TranscriptionStatus::SUCCESS, $result->getResults()[0]->transcriptionStatus);
    }

    public function testFromArrayCreatesObjectWithMultipleResults(): void
    {
        $data = [
            'results' => [
                [
                    'file_id' => self::VALID_UUID_1,
                    'status' => 'waiting',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
                [
                    'file_id' => self::VALID_UUID_2,
                    'status' => 'processing',
                    'file_size' => 200,
                    'file_duration' => 60,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
                [
                    'file_id' => self::VALID_UUID_3,
                    'status' => 'success',
                    'file_size' => 150,
                    'file_duration' => 45,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertCount(3, $result->getResults());
        $this->assertContainsOnlyInstancesOf(StatusItemResult::class, $result->getResults());

        // Verify order is preserved
        $this->assertSame(self::VALID_UUID_1, $result->getResults()[0]->fileId->toRfc4122());
        $this->assertSame(self::VALID_UUID_2, $result->getResults()[1]->fileId->toRfc4122());
        $this->assertSame(self::VALID_UUID_3, $result->getResults()[2]->fileId->toRfc4122());
    }

    public function testFromArrayHandlesDifferentPaginationValues(): void
    {
        $data = [
            'results' => [],
            'pagination' => [
                'page' => 3,
                'per_page' => 25,
                'total_pages' => 10,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertSame(3, $result->pagination->page);
        $this->assertSame(25, $result->pagination->perPage);
        $this->assertSame(10, $result->pagination->total);
    }

    public function testFromArrayHandlesMixedStatuses(): void
    {
        $data = [
            'results' => [
                [
                    'file_id' => self::VALID_UUID_1,
                    'status' => 'waiting',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
                [
                    'file_id' => self::VALID_UUID_2,
                    'status' => 'processing',
                    'file_size' => 200,
                    'file_duration' => 60,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
                [
                    'file_id' => self::VALID_UUID_3,
                    'status' => 'success',
                    'file_size' => 150,
                    'file_duration' => 45,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertSame(TranscriptionStatus::WAITING, $result->getResults()[0]->transcriptionStatus);
        $this->assertSame(TranscriptionStatus::PROCESSING, $result->getResults()[1]->transcriptionStatus);
        $this->assertSame(TranscriptionStatus::SUCCESS, $result->getResults()[2]->transcriptionStatus);
    }

    // fromArray() - Missing/Invalid Data Tests

    public function testFromArrayDefaultsToEmptyResultsWhenResultsKeyMissing(): void
    {
        $data = [
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 0,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertSame([], $result->getResults());
        $this->assertSame(1, $result->pagination->page);
    }

    public function testFromArrayThrowsExceptionWhenPaginationKeyMissing(): void
    {
        $data = [
            'results' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than or equal to 1');

        StatusItemListResult::fromArray($data);
    }

    public function testFromArrayThrowsExceptionForInvalidResultItem(): void
    {
        $data = [
            'results' => [
                [
                    // Missing file_id
                    'status' => 'success',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: file_id');

        StatusItemListResult::fromArray($data);
    }

    public function testFromArrayThrowsExceptionForInvalidPagination(): void
    {
        $data = [
            'results' => [],
            'pagination' => [
                // Missing page
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be greater than or equal to 1');

        StatusItemListResult::fromArray($data);
    }

    // getResults() Method Tests

    public function testGetResultsReturnsArrayOfStatusItemResult(): void
    {
        $data = [
            'results' => [
                [
                    'file_id' => self::VALID_UUID_1,
                    'status' => 'success',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
                [
                    'file_id' => self::VALID_UUID_2,
                    'status' => 'waiting',
                    'file_size' => 200,
                    'file_duration' => 60,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);
        $results = $result->getResults();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(StatusItemResult::class, $results);
    }

    public function testGetResultsReturnsConsistentData(): void
    {
        $data = [
            'results' => [
                [
                    'file_id' => self::VALID_UUID_1,
                    'status' => 'success',
                    'file_size' => 100,
                    'file_duration' => 30,
                    'timestamp_arrival' => self::VALID_TIMESTAMP,
                ],
            ],
            'pagination' => [
                'page' => 1,
                'per_page' => 10,
                'total_pages' => 1,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        // Call getResults() twice and verify they return the same data
        $firstCall = $result->getResults();
        $secondCall = $result->getResults();

        $this->assertSame($firstCall, $secondCall);
        $this->assertCount(1, $firstCall);
        $this->assertCount(1, $secondCall);
    }

    // Pagination Property Test

    public function testPaginationPropertyIsAccessible(): void
    {
        $data = [
            'results' => [],
            'pagination' => [
                'page' => 5,
                'per_page' => 20,
                'total_pages' => 15,
            ],
        ];

        $result = StatusItemListResult::fromArray($data);

        $this->assertInstanceOf(Pagination::class, $result->pagination);
        $this->assertSame(5, $result->pagination->page);
        $this->assertSame(20, $result->pagination->perPage);
        $this->assertSame(15, $result->pagination->total);
    }
}
