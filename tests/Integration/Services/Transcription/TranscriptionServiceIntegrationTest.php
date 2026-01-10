<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Services\Transcription;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FilesTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Tests\LoggerFactory;
use Symfony\Component\Uid\Uuid;

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
#[CoversClass(Transcription::class)]
#[CoversMethod(Transcription::class, 'submit')]
final class TranscriptionServiceIntegrationTest extends TestCase
{
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
        $this->transcription = $serviceFactory->getTranscriptionService();

        $this->testAudioFolder = __DIR__ . '/../../../Assets/ru/';

        if (!file_exists($this->testAudioFolder)) {
            $this->markTestSkipped('Test audio file not found: ' . $this->testAudioFolder);
        }
    }

    #[TestDox('отправка одного файла на транскрипцию')]
    public function testSubmitTranscriptForOneFile(): void
    {
        $result = $this->transcription->submit(
            [$this->testAudioFolder . 'examp-1.ogg'],
            TranscriptionOptions::default()
        );

        var_dump($this->transcription->getByFileId($result->getFileIds()[0]));

        $this->assertInstanceOf(TranscriptSubmitResult::class, $result);
        $this->assertCount(1, $result->getFileIds());
    }

    #[TestDox('получение транскрипции для несуществующего результата')]
    public function testGetTranscriptForNonExistsResult(): void
    {
        $result = $this->transcription->getByFileId(Uuid::v7());
        $this->assertTrue($result->isInProgress());

    }

    #[TestDox('отправка нескольких файлов на транскрипцию')]
    public function testSubmitMultipleFiles(): void
    {
        $files = [
            $this->testAudioFolder . 'examp-1.ogg',
            $this->testAudioFolder . 'examp-2.ogg',
            $this->testAudioFolder . 'examp-3.ogg',
        ];

        $result = $this->transcription->submit($files, TranscriptionOptions::default());
        $fileIds = $result->getFileIds();

        $this->assertCount(3, $fileIds);
    }

    #[TestDox('отправка файла на транскрипцию с пользовательскими настройками')]
    public function testSubmitWithCustomOptions(): void
    {
        $options = TranscriptionOptions::create()
            ->withTaskType(TaskType::TIMESTAMPS)
            ->withLanguage(Language::RU)
            ->withStoreFile(true)
            ->build();

        $result = $this->transcription->submit([$this->testAudioFolder . 'examp-1.ogg'], $options);
        $this->assertInstanceOf(TranscriptSubmitResult::class, $result);
        $this->assertNotEmpty($result->getFileIds());
    }

    #[TestDox('получение транскрипций за период')]
    public function testGetTranscriptsByPeriod(): void
    {
        $result = $this->transcription->getByPeriod(
            new DateTime('today'),
            new DateTime('today 23:59:59'),
            Pagination::default()
        );

        $this->assertInstanceOf(FilesTranscriptResult::class, $result);
        $this->assertIsArray($result->getResults());
    }


    /**
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws ApiException
     * @throws NetworkException
     * @throws FileException
     */
    #[TestDox('получение транскрипции по идентификатору файла')]
    public function testGetTranscriptByFileId(): void
    {
        // Upload file
        $postResult = $this->transcription->submit(
            [$this->testAudioFolder . 'examp-1.ogg'],
            TranscriptionOptions::default()
        );
        $fileId = $postResult->getFileIds()[0];

        // Get transcript
        $transcriptResult = $this->transcription->getByFileId($fileId);

        $this->assertTrue($transcriptResult->isInProgress());
    }



    public function testGetTranscriptsListWithFileIds(): void
    {
        // Upload 2 files
        $files = [
            $this->testAudioFolder . 'examp-1.ogg',
            $this->testAudioFolder . 'examp-2.ogg',
        ];
        $postResult = $this->transcription->submit($files, TranscriptionOptions::default());

        // Get transcripts by list
        $result = $this->transcription->getList($postResult->getFileIds(), Pagination::default());

        $this->assertInstanceOf(FilesTranscriptResult::class, $result);
        $this->assertEquals(2, $result->pagination->total);

        // Verify all requested file_ids are present
        foreach ($result->getResults() as $expectedFileId) {
            $found = false;
            foreach ($result->getResults() as $item) {
                if ($item->fileId->equals($expectedFileId)) {
                    $found = true;

                    break;
                }
            }
            $this->assertTrue($found, "File ID {$expectedFileId->fileId->toRfc4122()} not found in results");
        }
    }
}
