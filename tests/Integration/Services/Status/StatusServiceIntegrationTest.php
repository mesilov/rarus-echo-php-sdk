<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Services\Status;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Status\Result\StatusItemListResult;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Services\Status\Service\Status;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Tests\LoggerFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Integration tests for Status service
 *
 * These tests make real API calls to the RARUS Echo service.
 * Required environment variables:
 * - RARUS_ECHO_API_KEY: Your API key (UUID format)
 * - RARUS_ECHO_USER_ID: Your User ID (UUID format)
 * - RARUS_ECHO_BASE_URL: API base URL (optional)
 *
 * Required test files:
 * - tests/Assets/ru/examp-1.ogg
 *
 * Run with: docker compose run --rm php-cli vendor/bin/phpunit tests/Integration/Services/Status/
 */
#[CoversClass(Status::class)]
#[CoversMethod(Status::class, 'getByFileId')]
#[CoversMethod(Status::class, 'getByPeriod')]
#[CoversMethod(Status::class, 'getList')]
final class StatusServiceIntegrationTest extends TestCase
{
    private Status $status;
    private Transcription $transcription;
    private string $testAudioFolder;

    #[\Override]
    protected function setUp(): void
    {
        if (!isset($_ENV['RARUS_ECHO_API_KEY']) || !isset($_ENV['RARUS_ECHO_USER_ID'])) {
            $this->markTestSkipped(
                'Integration tests require RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID environment variables'
            );
        }

        $serviceFactory = ServiceFactory::fromEnvironment(LoggerFactory::defaultStdout());
        $this->status = $serviceFactory->getStatusService();
        $this->transcription = $serviceFactory->getTranscriptionService();

        $this->testAudioFolder = __DIR__ . '/../../../Assets/ru/';

        if (!file_exists($this->testAudioFolder . 'examp-1.ogg')) {
            $this->markTestSkipped('Test audio file not found: ' . $this->testAudioFolder . 'examp-1.ogg');
        }
    }

    #[TestDox('получение статуса для загруженного файла')]
    public function testGetFileStatusForUploadedFile(): void
    {
        $transcriptSubmitResult = $this->transcription->submit(
            [$this->testAudioFolder . 'examp-1.ogg'],
            TranscriptionOptions::default()
        );
        $fileId = $transcriptSubmitResult->getFileIds()[0];

        $statusResult = $this->status->getByFileId($fileId);

        $this->assertInstanceOf(StatusItemResult::class, $statusResult);
        $this->assertSame($fileId->toRfc4122(), $statusResult->fileId->toRfc4122());
        $this->assertInstanceOf(TranscriptionStatus::class, $statusResult->transcriptionStatus);
        $this->assertContains($statusResult->transcriptionStatus, [TranscriptionStatus::WAITING, TranscriptionStatus::PROCESSING]);
        $this->assertGreaterThanOrEqual(0, $statusResult->fileSize);
        $this->assertGreaterThanOrEqual(0, $statusResult->fileDuration);
        $this->assertInstanceOf(\DateTimeImmutable::class, $statusResult->timestampArrival);
        $this->assertFalse($statusResult->isCompleted());
        $this->assertFalse($statusResult->isSuccessful());
    }

    #[TestDox('получение статуса для несуществующего файла')]
    public function testGetFileStatusForNonExistingFile(): void
    {
        $fileId = Uuid::v7();

        $statusResult = $this->status->getByFileId($fileId);

        $this->assertInstanceOf(StatusItemResult::class, $statusResult);
        $this->assertSame($fileId->toRfc4122(), $statusResult->fileId->toRfc4122());
        $this->assertInstanceOf(TranscriptionStatus::class, $statusResult->transcriptionStatus);
    }

    #[TestDox('получение статусов пользователя за сегодня')]
    public function testGetUserStatusesForToday(): void
    {
        $startDate = new DateTime('today');
        $endDate = new DateTime('today 23:59:59');

        $result = $this->status->getByPeriod($startDate, $endDate, Pagination::default());

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertIsArray($result->getResults());
        $this->assertGreaterThanOrEqual(0, count($result->getResults()));
        $this->assertIsInt($result->pagination->page);
        $this->assertIsInt($result->pagination->perPage);
        $this->assertIsInt($result->pagination->total);

        foreach ($result->getResults() as $statusItem) {
            $this->assertInstanceOf(StatusItemResult::class, $statusItem);
        }
    }

    #[TestDox('получение статусов пользователя с пагинацией')]
    public function testGetUserStatusesWithPagination(): void
    {
        $files = [
            $this->testAudioFolder . 'examp-1.ogg',
            $this->testAudioFolder . 'examp-2.ogg',
        ];

        $this->transcription->submit($files, TranscriptionOptions::default());

        $startDate = new DateTime('today');
        $endDate = new DateTime('today 23:59:59');
        $pagination = new Pagination(page: 1, perPage: 10);

        $result = $this->status->getByPeriod($startDate, $endDate, $pagination);

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertSame(1, $result->pagination->page);
        $this->assertSame(10, $result->pagination->perPage);
        $this->assertIsInt($result->pagination->total);
    }

    #[TestDox('получение списка статусов по ID файлов')]
    public function testGetStatusListForUploadedFiles(): void
    {
        $files = [
            $this->testAudioFolder . 'examp-1.ogg',
            $this->testAudioFolder . 'examp-2.ogg',
        ];

        $transcriptSubmitResult = $this->transcription->submit($files, TranscriptionOptions::default());
        $fileIds = $transcriptSubmitResult->getFileIds();

        $result = $this->status->getList($fileIds, Pagination::default());

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertGreaterThanOrEqual(2, count($result->getResults()));

        $returnedFileIds = array_map(
            fn (StatusItemResult $item): string => $item->fileId->toRfc4122(),
            $result->getResults()
        );

        foreach ($fileIds as $fileId) {
            $this->assertContains($fileId->toRfc4122(), $returnedFileIds);
        }
    }

    #[TestDox('получение списка статусов с пагинацией')]
    public function testGetStatusListPagination(): void
    {
        $files = [
            $this->testAudioFolder . 'examp-1.ogg',
            $this->testAudioFolder . 'examp-2.ogg',
            $this->testAudioFolder . 'examp-3.ogg',
        ];

        $transcriptSubmitResult = $this->transcription->submit($files, TranscriptionOptions::default());
        $fileIds = $transcriptSubmitResult->getFileIds();

        $pagination = new Pagination(page: 1, perPage: 10);
        $result = $this->status->getList($fileIds, $pagination);

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertGreaterThanOrEqual(0, count($result->getResults()));
        $this->assertLessThanOrEqual(count($fileIds), count($result->getResults()));
        $this->assertGreaterThanOrEqual(0, count($result->getResults()));
    }

    #[TestDox('получение списка статусов с пустым массивом ID')]
    public function testGetStatusListWithEmptyArray(): void
    {
        $result = $this->status->getList([], Pagination::default());

        $this->assertInstanceOf(StatusItemListResult::class, $result);
        $this->assertIsArray($result->getResults());
    }
}
