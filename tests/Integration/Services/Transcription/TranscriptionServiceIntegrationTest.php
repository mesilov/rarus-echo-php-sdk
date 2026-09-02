<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Integration\Services\Transcription;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\TestDox;
use Rarus\Echo\Core\Pagination;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\AuthorizationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\NetworkException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FilesTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Rarus\Echo\Services\Transcription\Service\Transcription;
use Rarus\Echo\Tests\Integration\IntegrationTestCase;
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
 * - tests/Assets/ru/examp-1.ogg
 * - tests/Assets/ru/examp-2.ogg
 * - tests/Assets/ru/examp-3.ogg
 *
 * Run with: make test-integration-transcription
 * Or: docker compose run dev-php vendor/bin/phpunit tests/Integration/Services/Transcription/
 */
#[CoversClass(Transcription::class)]
#[CoversMethod(Transcription::class, 'submit')]
final class TranscriptionServiceIntegrationTest extends IntegrationTestCase
{
    private Transcription $transcription;

    #[\Override]
    protected function setUp(): void
    {
        $serviceFactory = $this->createServiceFactory();
        $this->transcription = $serviceFactory->getTranscriptionService();
    }

    #[TestDox('отправка одного файла на транскрипцию')]
    public function testSubmitTranscriptForOneFile(): void
    {
        $transcriptSubmitResult = $this->transcription->submit(
            [$this->testAudioPath('examp-1.ogg')],
            TranscriptionOptions::default()
        );

        $this->assertInstanceOf(TranscriptSubmitResult::class, $transcriptSubmitResult);
        $this->assertCount(1, $transcriptSubmitResult->getFileIds());
    }

    #[TestDox('получение транскрипции для несуществующего результата')]
    public function testGetTranscriptForNonExistsResult(): void
    {
        $fileItemTranscriptResult = $this->transcription->getByFileId(Uuid::v7());
        $this->assertTrue($fileItemTranscriptResult->isInProgress());
    }

    #[TestDox('отправка нескольких файлов на транскрипцию')]
    public function testSubmitMultipleFiles(): void
    {
        $files = $this->testAudioFiles('examp-1.ogg', 'examp-2.ogg', 'examp-3.ogg');

        $transcriptSubmitResult = $this->transcription->submit($files, TranscriptionOptions::default());
        $fileIds = $transcriptSubmitResult->getFileIds();

        $this->assertCount(3, $fileIds);
    }

    #[TestDox('отправка файла на транскрипцию с пользовательскими настройками')]
    public function testSubmitWithCustomOptions(): void
    {
        $transcriptionOptions = TranscriptionOptions::create()
            ->withTaskType(TaskType::TIMESTAMPS)
            ->withLanguage(Language::RU)
            ->withStoreFile(true)
            ->build();

        $transcriptSubmitResult = $this->transcription->submit([$this->testAudioPath('examp-1.ogg')], $transcriptionOptions);
        $this->assertInstanceOf(TranscriptSubmitResult::class, $transcriptSubmitResult);
        $this->assertNotEmpty($transcriptSubmitResult->getFileIds());
    }

    #[TestDox('отправка файла на диаризацию с расширенными таймкодами')]
    public function testSubmitDiarizationWithExtendedTimestamps(): void
    {
        $transcriptionOptions = TranscriptionOptions::create()
            ->withTaskType(TaskType::DIARIZATION)
            ->withLanguage(Language::RU)
            ->withSpeakersCorrection()
            ->withTimestampsExtended()
            ->build();

        try {
            $transcriptSubmitResult = $this->transcription->submit([$this->testAudioPath('examp-1.ogg')], $transcriptionOptions);
        } catch (AuthorizationException $exception) {
            if (str_contains($exception->getMessage(), 'Недостаточно средств')) {
                $this->markTestSkipped('Live API rejected the paid submit request: insufficient funds.');
            }

            throw $exception;
        }

        $this->assertInstanceOf(TranscriptSubmitResult::class, $transcriptSubmitResult);
        $this->assertCount(1, $transcriptSubmitResult->getFileIds());
    }

    #[TestDox('получение транскрипций за период')]
    public function testGetTranscriptsByPeriod(): void
    {
        $filesTranscriptResult = $this->transcription->getByPeriod(
            new DateTime('today'),
            new DateTime('today 23:59:59'),
            Pagination::default()
        );

        $this->assertInstanceOf(FilesTranscriptResult::class, $filesTranscriptResult);
        $this->assertIsArray($filesTranscriptResult->getResults());
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
        $transcriptSubmitResult = $this->transcription->submit(
            [$this->testAudioPath('examp-1.ogg')],
            TranscriptionOptions::default()
        );
        $fileId = $transcriptSubmitResult->getFileIds()[0];

        // Get transcript
        $fileItemTranscriptResult = $this->transcription->getByFileId($fileId);

        $this->assertTrue($fileItemTranscriptResult->isInProgress() || $fileItemTranscriptResult->isSuccessful());
    }

    public function testGetTranscriptsListWithFileIds(): void
    {
        // Upload 2 files
        $files = $this->testAudioFiles('examp-1.ogg', 'examp-2.ogg');
        $transcriptSubmitResult = $this->transcription->submit($files, TranscriptionOptions::default());

        // Get transcripts by list
        $filesTranscriptResult = $this->transcription->getList($transcriptSubmitResult->getFileIds(), Pagination::default());

        $this->assertInstanceOf(FilesTranscriptResult::class, $filesTranscriptResult);
        $this->assertCount(2, $filesTranscriptResult->getResults());
        // Verify all requested file_ids are present
        foreach ($transcriptSubmitResult->getFileIds() as $fileIdSubmitResult) {
            $found = false;
            foreach ($filesTranscriptResult->getResults() as $item) {
                if ($item->fileId->equals($fileIdSubmitResult)) {
                    $found = true;

                    break;
                }
            }
            $this->assertTrue($found, "File ID {$fileIdSubmitResult->toRfc4122()} not found in results");
        }
    }
}
