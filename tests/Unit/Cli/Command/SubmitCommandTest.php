<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Cli\Command\SubmitCommand;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;
use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class SubmitCommandTest extends TestCase
{
    private const string FILE_ID = '11111111-1111-1111-1111-111111111111';

    public function testSubmitsWithDefaultOptionsAsText(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

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
        $this->assertNull($options->getRequestSource());
    }

    public function testSubmitsWithExplicitOptions(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::SUCCESS,
            $tester->execute([
                'files' => ['audio.ogg', 'video.mp4'],
                '--task-type' => 'diarization',
                '--language' => 'ru',
                '--censor' => true,
                '--speakers-correction' => true,
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
        $this->assertSame('cli', $options->getRequestSource());
    }

    public function testOutputsSubmitResultAsJson(): void
    {
        $client = $this->clientWithSubmitResult();
        $tester = new CommandTester(new SubmitCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['files' => ['audio.ogg'], '--json' => true]));
        $this->assertSame(
            ['file_ids' => [self::FILE_ID]],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
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

    private function clientWithSubmitResult(): FakeEchoClient
    {
        $client = new FakeEchoClient();
        $client->submitResult = new TranscriptSubmitResult([
            Uuid::fromString(self::FILE_ID),
        ]);

        return $client;
    }
}
