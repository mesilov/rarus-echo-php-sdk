<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Cli\Command\TranscriptCommand;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class TranscriptCommandTest extends TestCase
{
    private const string FILE_ID = '11111111-1111-1111-1111-111111111111';

    public function testOutputsTranscriptAsText(): void
    {
        $client = $this->clientWithTranscript();
        $tester = new CommandTester(new TranscriptCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['file-id' => self::FILE_ID]));
        $this->assertSame(
            implode("\n", [
                'file_id: ' . self::FILE_ID,
                'status: success',
                'task_type: diarization',
                'result:',
                'hello from transcript',
                '',
            ]),
            $tester->getDisplay(true)
        );
        $this->assertSame(self::FILE_ID, $client->lastTranscriptFileId?->toRfc4122());
    }

    public function testOutputsTranscriptAsJson(): void
    {
        $client = $this->clientWithTranscript();
        $tester = new CommandTester(new TranscriptCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['file-id' => self::FILE_ID, '--json' => true]));
        $this->assertSame(
            [
                'file_id' => self::FILE_ID,
                'status' => 'success',
                'task_type' => 'diarization',
                'result' => 'hello from transcript',
            ],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidFileIdWritesErrorWithoutCreatingClient(): void
    {
        $client = new FakeEchoClient();
        $factory = new FakeEchoClientFactory($client);
        $tester = new CommandTester(new TranscriptCommand($factory));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(['file-id' => 'not-a-uuid'], ['capture_stderr_separately' => true])
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('file-id must be a valid UUID', $tester->getErrorOutput(true));
        $this->assertSame(0, $factory->calls);
    }

    public function testWritesServiceFailureToStderr(): void
    {
        $client = new FakeEchoClient();
        $client->exception = new \RuntimeException('Service unavailable');
        $tester = new CommandTester(new TranscriptCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(['file-id' => self::FILE_ID], ['capture_stderr_separately' => true])
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Error: Service unavailable', $tester->getErrorOutput(true));
    }

    private function clientWithTranscript(): FakeEchoClient
    {
        $client = new FakeEchoClient();
        $client->transcript = new FileItemTranscriptResult(
            fileId: Uuid::fromString(self::FILE_ID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            taskType: TaskType::DIARIZATION,
            result: 'hello from transcript'
        );

        return $client;
    }
}
