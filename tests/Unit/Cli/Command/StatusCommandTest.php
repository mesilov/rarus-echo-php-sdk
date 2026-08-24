<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli\Command;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Cli\Command\StatusCommand;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Services\Status\Result\StatusItemResult;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class StatusCommandTest extends TestCase
{
    private const string FILE_ID = '11111111-1111-1111-1111-111111111111';

    public function testOutputsStatusAsText(): void
    {
        $client = $this->clientWithStatus();
        $tester = new CommandTester(new StatusCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['file-id' => self::FILE_ID]));
        $this->assertSame(
            implode("\n", [
                'file_id: ' . self::FILE_ID,
                'status: success',
                'file_size: 123',
                'file_duration: 45',
                'timestamp_arrival: 2026-08-24T06:00:00+00:00',
                '',
            ]),
            $tester->getDisplay(true)
        );
        $this->assertSame(self::FILE_ID, $client->lastStatusFileId?->toRfc4122());
    }

    public function testOutputsStatusAsJson(): void
    {
        $client = $this->clientWithStatus();
        $tester = new CommandTester(new StatusCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['file-id' => self::FILE_ID, '--json' => true]));
        $this->assertSame(
            [
                'file_id' => self::FILE_ID,
                'status' => 'success',
                'file_size' => 123,
                'file_duration' => 45,
                'timestamp_arrival' => '2026-08-24T06:00:00+00:00',
            ],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testInvalidFileIdWritesErrorWithoutCreatingClient(): void
    {
        $client = new FakeEchoClient();
        $factory = new FakeEchoClientFactory($client);
        $tester = new CommandTester(new StatusCommand($factory));

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
        $tester = new CommandTester(new StatusCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute(['file-id' => self::FILE_ID], ['capture_stderr_separately' => true])
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Error: Service unavailable', $tester->getErrorOutput(true));
    }

    private function clientWithStatus(): FakeEchoClient
    {
        $client = new FakeEchoClient();
        $client->status = new StatusItemResult(
            fileId: Uuid::fromString(self::FILE_ID),
            transcriptionStatus: TranscriptionStatus::SUCCESS,
            fileSize: 123,
            fileDuration: 45,
            timestampArrival: new DateTimeImmutable('2026-08-24T06:00:00+00:00')
        );

        return $client;
    }
}
