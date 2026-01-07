<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Services\Transcription;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\TranscriptBatchResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptItemResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptPostResult;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Tests\LoggerFactory;

/**
 * Integration tests for Transcription service
 *
 * These tests make real API calls to the RARUS Echo service.
 * They upload test audio files from tests/Assets/ and verify API responses.
 *
 * Note: These tests DO NOT wait for transcription completion (too slow).
 * They only verify that:
 * - Files are uploaded successfully
 * - API returns correct response structures
 * - File IDs are generated and can be queried
 *
 * Required environment variables:
 * - RARUS_ECHO_API_KEY: Your API key (UUID format)
 * - RARUS_ECHO_USER_ID: Your User ID (UUID format)
 * - RARUS_ECHO_BASE_URL: API base URL (optional)
 *
 * Required test files:
 * - tests/Assets/examp-1.ogg
 * - tests/Assets/examp-2.ogg
 * - tests/Assets/examp-3.ogg
 *
 * Run with: make test-integration-transcription
 * Or: docker compose run php-cli vendor/bin/phpunit tests/Integration/Services/Transcription/
 */
final class TranscriptionServiceIntegrationTest extends TestCase
{
    private Transcription $transcription;
    private string $testAudioFile;

    #[\Override]
    protected function setUp(): void
    {
        if (!isset($_ENV['RARUS_ECHO_API_KEY']) || !isset($_ENV['RARUS_ECHO_USER_ID'])) {
            $this->markTestSkipped(
                'Integration tests require RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID environment variables'
            );
        }

        $serviceFactory = ServiceFactory::fromEnvironment(LoggerFactory::defaultStdout());
        $this->transcription = $serviceFactory->getTranscriptionService();

        $this->testAudioFile = __DIR__ . '/../../../Assets/examp-1.ogg';

        if (!file_exists($this->testAudioFile)) {
            $this->markTestSkipped('Test audio file not found: ' . $this->testAudioFile);
        }
    }

    public function testSubmitTranscriptionReturnsFileIds(): void
    {
        $options = TranscriptionOptions::default();
        $result = $this->transcription->submitTranscription(
            [$this->testAudioFile],
            $options
        );

        $this->assertInstanceOf(TranscriptPostResult::class, $result);
        $fileIds = $result->getFileIds();
        $this->assertCount(1, $fileIds);
        $this->assertNotEmpty($fileIds[0]);
        $this->assertIsString($fileIds[0]);
    }

    public function testSubmitMultipleFiles(): void
    {
        $files = [
            __DIR__ . '/../../../Assets/examp-1.ogg',
            __DIR__ . '/../../../Assets/examp-2.ogg',
            __DIR__ . '/../../../Assets/examp-3.ogg',
        ];

        $result = $this->transcription->submitTranscription($files, TranscriptionOptions::default());
        $fileIds = $result->getFileIds();

        $this->assertCount(3, $fileIds);
        foreach ($fileIds as $fileId) {
            $this->assertNotEmpty($fileId);
            $this->assertIsString($fileId);
        }
    }

    public function testSubmitWithCustomOptions(): void
    {
        $options = TranscriptionOptions::create()
            ->withTaskType(TaskType::TIMESTAMPS)
            ->withLanguage(Language::RU)
            ->withStoreFile(true)
            ->build();

        $result = $this->transcription->submitTranscription([$this->testAudioFile], $options);
        $this->assertInstanceOf(TranscriptPostResult::class, $result);
        $this->assertNotEmpty($result->getFileIds());
    }

    public function testGetTranscriptByFileId(): void
    {
        // Upload file
        $postResult = $this->transcription->submitTranscription(
            [$this->testAudioFile],
            TranscriptionOptions::default()
        );
        $fileId = $postResult->getFileIds()[0];

        // Get transcript (probably still processing)
        $transcript = $this->transcription->getTranscript($fileId);

        $this->assertInstanceOf(TranscriptItemResult::class, $transcript);
        $this->assertSame($fileId, $transcript->getFileId());
        $this->assertInstanceOf(TranscriptionStatus::class, $transcript->getStatus());
        $this->assertInstanceOf(TaskType::class, $transcript->getTaskType());

        // Result may be empty if still processing
        $this->assertIsString($transcript->getResult());
    }

    public function testGetTranscriptsByPeriod(): void
    {
        $startDate = new \DateTime('today');
        $endDate = new \DateTime('today 23:59:59');
        $pagination = new Pagination(page: 1, perPage: 10);

        $result = $this->transcription->getTranscriptsByPeriod($startDate, $endDate, $pagination);

        $this->assertInstanceOf(TranscriptBatchResult::class, $result);
        $this->assertIsArray($result->getResults());
        $this->assertIsInt($result->getPage());
        $this->assertIsInt($result->getPerPage());
        $this->assertIsInt($result->getTotalPages());
        $this->assertGreaterThanOrEqual(0, $result->getCount());
    }

    public function testGetTranscriptsListWithFileIds(): void
    {
        // Upload 2 files
        $files = [
            __DIR__ . '/../../../Assets/examp-1.ogg',
            __DIR__ . '/../../../Assets/examp-2.ogg',
        ];
        $postResult = $this->transcription->submitTranscription($files, TranscriptionOptions::default());
        $fileIds = $postResult->getFileIds();

        // Get transcripts by list
        $pagination = new Pagination(page: 1, perPage: 10);
        $result = $this->transcription->getTranscriptsList($fileIds, $pagination);

        $this->assertInstanceOf(TranscriptBatchResult::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->getCount());

        // Verify all requested file_ids are present
        foreach ($fileIds as $expectedFileId) {
            $found = false;
            foreach ($result->getResults() as $item) {
                if ($item->getFileId() === $expectedFileId) {
                    $found = true;

                    break;
                }
            }
            $this->assertTrue($found, "File ID {$expectedFileId} not found in results");
        }
    }
}
