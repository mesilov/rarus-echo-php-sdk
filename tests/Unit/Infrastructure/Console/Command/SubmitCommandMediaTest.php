<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Infrastructure\Console\Command\SubmitCommand;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Infrastructure\Console\Media\MediaPreparationInterface;
use Rarus\Echo\Infrastructure\Console\Media\PreparedFileSet;
use Rarus\Echo\Infrastructure\Console\TranscriptPoller;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SubmitCommandMediaTest extends TestCase
{
    public function testInvalidMediaOptionsFailBeforeResolvingConnection(): void
    {
        foreach ([['--audio-stream' => '-1'], ['--audio-stream' => '1.5'], ['--audio-stream' => '0', '--no-extract-audio' => true], ['--media-timeout' => '0'], ['--media-timeout' => '999999999999999999999999']] as $options) {
            $factory = new FakeEchoClientFactory(new FakeEchoClient());
            $tester = new CommandTester(new SubmitCommand($factory));
            self::assertSame(Command::FAILURE, $tester->execute(array_merge(['files' => ['source.mp4']], $options), ['capture_stderr_separately' => true]));
            self::assertSame(0, $factory->calls);
            self::assertSame('', $tester->getDisplay());
            self::assertStringContainsString('Error:', $tester->getErrorOutput());
        }
    }

    public function testManualStreamRejectsBatchBeforeResolvingConnection(): void
    {
        $factory = new FakeEchoClientFactory(new FakeEchoClient());
        $tester = new CommandTester(new SubmitCommand($factory));
        self::assertSame(Command::FAILURE, $tester->execute(['files' => ['one.mp4', 'two.mp4'], '--audio-stream' => '0'], ['capture_stderr_separately' => true]));
        self::assertSame(0, $factory->calls);
        self::assertStringContainsString('one video input', $tester->getErrorOutput());
    }

    public function testUploadFailureCleansPreparedFilesAndKeepsOriginalAssociation(): void
    {
        $prepared = new PreparedFileSet();
        $path = $prepared->allocate('flac');
        file_put_contents($path, 'prepared fixture');
        $prepared->add('source.mp4', $path);
        $prepared->add('audio.ogg', 'audio.ogg');
        $preparer = new class ($prepared) implements MediaPreparationInterface {
            public function __construct(private readonly PreparedFileSet $prepared)
            {
            }

            public function prepare(array $files, MediaOptions $options, \Closure $progress): PreparedFileSet
            {
                return $this->prepared;
            }

            public function cancel(): void
            {
            }
        };
        $client = new FakeEchoClient();
        $client->exception = new \RuntimeException('upload failed');
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client), mediaPreparer: $preparer));
        self::assertSame(Command::FAILURE, $tester->execute(['files' => ['source.mp4', 'audio.ogg']], ['capture_stderr_separately' => true]));
        self::assertSame([$path, 'audio.ogg'], $client->lastSubmittedFiles);
        self::assertFileDoesNotExist($path);
        self::assertSame([], $client->transcriptCalls);
    }

    public function testPreparedOutputIsRemovedBeforePollingBegins(): void
    {
        $prepared = new PreparedFileSet();
        $path = $prepared->allocate('flac');
        file_put_contents($path, 'prepared fixture');
        $prepared->add('source.mp4', $path);
        $preparer = new class ($prepared) implements MediaPreparationInterface {
            public function __construct(private readonly PreparedFileSet $prepared)
            {
            }

            public function prepare(array $files, MediaOptions $options, \Closure $progress): PreparedFileSet
            {
                return $this->prepared;
            }

            public function cancel(): void
            {
            }
        };
        $id = Uuid::fromString('11111111-1111-1111-1111-111111111111');
        $client = new FakeEchoClient();
        $client->submitResult = new TranscriptSubmitResult([$id]);
        $client->transcript = new FileItemTranscriptResult($id, TranscriptionStatus::SUCCESS, TaskType::TRANSCRIPTION, 'done');
        $poller = new TranscriptPoller(static function (int $seconds): void {
        }, static function () use ($path): int {
            self::assertFileDoesNotExist($path);

            return 0;
        });
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client), poller: $poller, mediaPreparer: $preparer));
        self::assertSame(Command::SUCCESS, $tester->execute(['files' => ['source.mp4'], '--wait' => true, '--json' => true], ['capture_stderr_separately' => true]));
        self::assertSame([$id->toRfc4122()], $client->transcriptCalls);
        self::assertSame('done', json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['results'][0]['result']);
    }
}
