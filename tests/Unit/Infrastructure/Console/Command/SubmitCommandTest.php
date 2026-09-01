<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Infrastructure\Console\ApplicationFactory;
use Rarus\Echo\Infrastructure\Console\Command\SubmitCommand;
use Rarus\Echo\Infrastructure\Console\TranscriptPoller;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SubmitCommandTest extends TestCase
{
    private const string FILE_ID = '11111111-1111-1111-1111-111111111111';
    private const string SECOND_FILE_ID = '22222222-2222-2222-2222-222222222222';

    public function testSubmitsWithDefaultOptionsAsText(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(Command::SUCCESS, $tester->execute(['files' => ['audio.ogg']]));
        $this->assertSame(
            implode("\n", [
                'file_ids:',
                '- ' . self::FILE_ID,
                '',
            ]),
            $tester->getDisplay(true)
        );
        $this->assertSame(['audio.ogg'], $client->lastSubmittedFiles);

        $options = $client->lastTranscriptionOptions;
        $this->assertInstanceOf(TranscriptionOptions::class, $options);
        $this->assertSame(TaskType::TRANSCRIPTION, $options->getTaskType());
        $this->assertSame(Language::AUTO, $options->getLanguage());
        $this->assertFalse($options->isCensor());
        $this->assertFalse($options->isSpeakersCorrection());
        $this->assertTrue($options->isStoreFile());
        $this->assertFalse($options->isLowPriority());
        $this->assertFalse($options->isTimestampsExtended());
        $this->assertNull($options->getRequestSource());
    }

    public function testSubmitsWithExplicitOptions(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(
            Command::SUCCESS,
            $tester->execute([
                'files' => ['audio.ogg', 'video.mp4'],
                '--task-type' => 'diarization',
                '--language' => 'ru',
                '--censor' => true,
                '--speakers-correction' => true,
                '--timestamps-extended' => true,
                '--no-store-file' => true,
                '--low-priority' => true,
                '--request-source' => 'cli',
            ])
        );
        $this->assertSame(['audio.ogg', 'video.mp4'], $client->lastSubmittedFiles);

        $options = $client->lastTranscriptionOptions;
        $this->assertInstanceOf(TranscriptionOptions::class, $options);
        $this->assertSame(TaskType::DIARIZATION, $options->getTaskType());
        $this->assertSame(Language::RU, $options->getLanguage());
        $this->assertTrue($options->isCensor());
        $this->assertTrue($options->isSpeakersCorrection());
        $this->assertFalse($options->isStoreFile());
        $this->assertTrue($options->isLowPriority());
        $this->assertTrue($options->isTimestampsExtended());
        $this->assertSame('cli', $options->getRequestSource());
    }

    public function testHelpDocumentsTimestampsExtendedOption(): void
    {
        $application = ApplicationFactory::create(new FakeEchoClientFactory(new FakeEchoClient()));
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $this->assertSame(Command::SUCCESS, $tester->run(['command' => 'submit', '--help' => true]));
        $this->assertStringContainsString('--timestamps-extended', $tester->getDisplay(true));
    }

    public function testHelpDocumentsWaitOptions(): void
    {
        $application = ApplicationFactory::create(new FakeEchoClientFactory(new FakeEchoClient()));
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $this->assertSame(Command::SUCCESS, $tester->run(['command' => 'submit', '--help' => true]));
        $help = $tester->getDisplay(true);

        $this->assertStringContainsString('--wait', $help);
        $this->assertStringContainsString('--poll-interval', $help);
        $this->assertStringContainsString('--timeout', $help);
        $this->assertStringContainsString('--raw-result', $help);
        $this->assertStringContainsString('--output', $help);
    }

    public function testOutputsSubmitResultAsJson(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(Command::SUCCESS, $tester->execute(['files' => ['audio.ogg'], '--json' => true]));
        $this->assertSame(
            ['file_ids' => [self::FILE_ID]],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testWaitsForTranscriptAndOutputsFinalJson(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcriptResults = [
            self::FILE_ID => [
                $this->transcriptResult(self::FILE_ID, TranscriptionStatus::WAITING),
                $this->transcriptResult(self::FILE_ID, TranscriptionStatus::PROCESSING),
                $this->transcriptResult(self::FILE_ID, TranscriptionStatus::SUCCESS, 'Final transcript'),
            ],
        ];
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(
            Command::SUCCESS,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--json' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '5',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame(
            [
                'file_ids' => [self::FILE_ID],
                'results' => [
                    [
                        'file_id' => self::FILE_ID,
                        'status' => 'success',
                        'task_type' => 'transcription',
                        'result' => 'Final transcript',
                    ],
                ],
            ],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
        $this->assertStringNotContainsString('polling:', $tester->getDisplay(true));
        $stderr = $tester->getErrorOutput(true);
        $this->assertStringContainsString('submitted: ' . self::FILE_ID, $stderr);
        $this->assertStringContainsString('polling: attempt=1 file_id=' . self::FILE_ID . ' status=waiting', $stderr);
        $this->assertStringContainsString('polling: attempt=2 file_id=' . self::FILE_ID . ' status=processing', $stderr);
        $this->assertStringContainsString('completed: ' . self::FILE_ID . ' status=success', $stderr);
        $this->assertSame([self::FILE_ID, self::FILE_ID, self::FILE_ID], $client->transcriptCalls);
    }

    public function testWaitsForMultipleTranscriptsAndOutputsFinalJson(): void
    {
        $client = $this->clientWithSubmitResult([self::FILE_ID, self::SECOND_FILE_ID]);
        $client->transcriptResults = [
            self::FILE_ID => [
                $this->transcriptResult(self::FILE_ID, TranscriptionStatus::SUCCESS, 'First transcript'),
            ],
            self::SECOND_FILE_ID => [
                $this->transcriptResult(self::SECOND_FILE_ID, TranscriptionStatus::SUCCESS, 'Second transcript'),
            ],
        ];
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::SUCCESS,
            $tester->execute(
                [
                    'files' => ['audio-1.ogg', 'audio-2.ogg'],
                    '--wait' => true,
                    '--json' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '5',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame(
            [
                'file_ids' => [self::FILE_ID, self::SECOND_FILE_ID],
                'results' => [
                    [
                        'file_id' => self::FILE_ID,
                        'status' => 'success',
                        'task_type' => 'transcription',
                        'result' => 'First transcript',
                    ],
                    [
                        'file_id' => self::SECOND_FILE_ID,
                        'status' => 'success',
                        'task_type' => 'transcription',
                        'result' => 'Second transcript',
                    ],
                ],
            ],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
        $this->assertStringContainsString('submitted: ' . self::FILE_ID, $tester->getErrorOutput(true));
        $this->assertStringContainsString('submitted: ' . self::SECOND_FILE_ID, $tester->getErrorOutput(true));
    }

    public function testWaitRawResultWritesOnlyTranscriptTextToStdout(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcript = $this->transcriptResult(
            self::FILE_ID,
            TranscriptionStatus::SUCCESS,
            'Hello <info>literal</info> transcript'
        );
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::SUCCESS,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--raw-result' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '5',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame("Hello <info>literal</info> transcript\n", $tester->getDisplay(true));
        $this->assertStringContainsString('completed: ' . self::FILE_ID . ' status=success', $tester->getErrorOutput(true));
    }

    public function testWaitOutputWritesTranscriptToFile(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcript = $this->transcriptResult(self::FILE_ID, TranscriptionStatus::SUCCESS, 'File transcript');
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));
        $outputPath = tempnam(sys_get_temp_dir(), 'rarus-echo-transcript-');
        self::assertIsString($outputPath);

        try {
            $this->assertSame(
                Command::SUCCESS,
                $tester->execute(
                    [
                        'files' => ['audio.ogg'],
                        '--wait' => true,
                        '--output' => $outputPath,
                        '--poll-interval' => '1',
                        '--timeout' => '5',
                    ],
                    ['capture_stderr_separately' => true]
                )
            );

            $this->assertSame('', $tester->getDisplay(true));
            $this->assertSame('File transcript', file_get_contents($outputPath));
            $this->assertStringContainsString('completed: ' . self::FILE_ID . ' status=success', $tester->getErrorOutput(true));
        } finally {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
        }
    }

    public function testInvalidWaitOptionsFailBeforeCreatingClient(): void
    {
        $cases = [
            [
                ['files' => ['audio.ogg'], '--raw-result' => true],
                '--raw-result requires --wait',
            ],
            [
                ['files' => ['audio.ogg'], '--output' => 'transcript.txt'],
                '--output requires --wait',
            ],
            [
                ['files' => ['audio-1.ogg', 'audio-2.ogg'], '--wait' => true, '--raw-result' => true],
                '--raw-result supports only one submitted file',
            ],
            [
                ['files' => ['audio-1.ogg', 'audio-2.ogg'], '--wait' => true, '--output' => 'transcript.txt'],
                '--output supports only one submitted file',
            ],
            [
                ['files' => ['audio.ogg'], '--wait' => true, '--poll-interval' => '0'],
                '--poll-interval must be a positive integer',
            ],
            [
                ['files' => ['audio.ogg'], '--wait' => true, '--timeout' => '0'],
                '--timeout must be a positive integer',
            ],
        ];

        foreach ($cases as [$arguments, $expectedError]) {
            $factory = new FakeEchoClientFactory(new FakeEchoClient());
            $tester = new CommandTester(new SubmitCommand($factory, $this->noSleepPoller()));

            $this->assertSame(
                Command::FAILURE,
                $tester->execute($arguments, ['capture_stderr_separately' => true]),
                $expectedError
            );
            $this->assertSame('', $tester->getDisplay(true), $expectedError);
            $this->assertStringContainsString($expectedError, $tester->getErrorOutput(true));
            $this->assertSame(0, $factory->calls, $expectedError);
        }
    }

    public function testWaitTimeoutFailsWithLastKnownState(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcript = $this->transcriptResult(self::FILE_ID, TranscriptionStatus::PROCESSING);
        $timestamps = [0, 0, 1];
        $tester = new CommandTester($this->submitCommand(
            $client,
            $this->noSleepPoller(static function () use (&$timestamps): int {
                return array_shift($timestamps) ?? 1;
            })
        ));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--json' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '1',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame('', $tester->getDisplay(true));
        $stderr = $tester->getErrorOutput(true);
        $this->assertStringContainsString('Error: Timeout after 1 seconds while waiting for transcript results.', $stderr);
        $this->assertStringContainsString('last_status: file_id=' . self::FILE_ID . ' status=processing', $stderr);
    }

    public function testWaitDoesNotAcceptSuccessReturnedAfterTimeout(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcript = $this->transcriptResult(self::FILE_ID, TranscriptionStatus::SUCCESS, 'Late transcript');
        $timestamps = [0, 0, 2];
        $tester = new CommandTester($this->submitCommand(
            $client,
            $this->noSleepPoller(static function () use (&$timestamps): int {
                return array_shift($timestamps) ?? 2;
            })
        ));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--json' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '1',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString(
            'Error: Timeout after 1 seconds while waiting for transcript results.',
            $tester->getErrorOutput(true)
        );
        $this->assertStringNotContainsString('completed: ' . self::FILE_ID, $tester->getErrorOutput(true));
    }

    public function testWaitTerminalFailureFailsWithFileStatus(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcript = $this->transcriptResult(self::FILE_ID, TranscriptionStatus::FAILURE);
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '5',
                ],
                ['capture_stderr_separately' => true]
            )
        );

        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString(
            'Error: Transcript processing failed: file_id=' . self::FILE_ID . ' status=failure',
            $tester->getErrorOutput(true)
        );
    }

    public function testWaitWritesPollingServiceFailureToStderr(): void
    {
        $client = $this->clientWithSubmitResult();
        $client->transcriptException = new \RuntimeException('Transcript API unavailable');
        $tester = new CommandTester($this->submitCommand($client));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                [
                    'files' => ['audio.ogg'],
                    '--wait' => true,
                    '--poll-interval' => '1',
                    '--timeout' => '5',
                ],
                ['capture_stderr_separately' => true]
            )
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Error: Transcript API unavailable', $tester->getErrorOutput(true));
    }

    public function testWaitSignalHandlerWritesShutdownMessageToStderr(): void
    {
        if (!\defined('SIGINT') || !\defined('SIGTERM')) {
            self::markTestSkipped('PCNTL signal constants are unavailable.');
        }

        $command = $this->submitCommand($this->clientWithSubmitResult());
        $errorOutput = new BufferedOutput();
        $signalErrorOutput = new \ReflectionProperty($command, 'signalErrorOutput');
        $signalErrorOutput->setValue($command, $errorOutput);

        $this->assertContains(\SIGINT, $command->getSubscribedSignals());
        $this->assertContains(\SIGTERM, $command->getSubscribedSignals());
        $this->assertSame(128 + \SIGINT, $command->handleSignal(\SIGINT));
        $this->assertSame("Signal SIGINT received, shutting down.\n", $errorOutput->fetch());
        $this->assertSame(128 + \SIGTERM, $command->handleSignal(\SIGTERM));
        $this->assertSame("Signal SIGTERM received, shutting down.\n", $errorOutput->fetch());
    }

    public function testInvalidTaskTypeWritesErrorWithoutCreatingClient(): void
    {
        $client = new FakeEchoClient();
        $factory = new FakeEchoClientFactory($client);
        $tester = new CommandTester(new SubmitCommand($factory));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                ['files' => ['audio.ogg'], '--task-type' => 'unknown'],
                ['capture_stderr_separately' => true]
            )
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Invalid task type "unknown"', $tester->getErrorOutput(true));
        $this->assertSame(0, $factory->calls);
    }

    public function testInvalidLanguageWritesErrorWithoutCreatingClient(): void
    {
        $client = new FakeEchoClient();
        $factory = new FakeEchoClientFactory($client);
        $tester = new CommandTester(new SubmitCommand($factory));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(
                ['files' => ['audio.ogg'], '--language' => 'xx'],
                ['capture_stderr_separately' => true]
            )
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Invalid language "xx"', $tester->getErrorOutput(true));
        $this->assertSame(0, $factory->calls);
    }

    public function testWritesServiceFailureToStderr(): void
    {
        $client = new FakeEchoClient();
        $client->exception = new \RuntimeException('Service unavailable');
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(['files' => ['audio.ogg']], ['capture_stderr_separately' => true])
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Error: Service unavailable', $tester->getErrorOutput(true));
    }

    /**
     * @param list<string> $fileIds
     */
    private function clientWithSubmitResult(array $fileIds = [self::FILE_ID]): FakeEchoClient
    {
        $client = new FakeEchoClient();
        $client->submitResult = new TranscriptSubmitResult(array_map(
            static fn (string $fileId): Uuid => Uuid::fromString($fileId),
            $fileIds
        ));

        return $client;
    }

    private function transcriptResult(
        string $fileId,
        TranscriptionStatus $status,
        ?string $result = null,
        ?TaskType $taskType = TaskType::TRANSCRIPTION
    ): FileItemTranscriptResult {
        return new FileItemTranscriptResult(
            fileId: Uuid::fromString($fileId),
            transcriptionStatus: $status,
            taskType: $taskType,
            result: $result
        );
    }

    private function submitCommand(FakeEchoClient $client, ?TranscriptPoller $poller = null): SubmitCommand
    {
        return new SubmitCommand(new FakeEchoClientFactory($client), $poller ?? $this->noSleepPoller());
    }

    private function noSleepPoller(?\Closure $time = null): TranscriptPoller
    {
        return new TranscriptPoller(
            static function (int $seconds): void {
            },
            $time ?? static fn (): int => 0
        );
    }
}
