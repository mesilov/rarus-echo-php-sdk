<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Command\QueueCommand;
use Rarus\Echo\Services\Queue\Result\QueueInfoResult;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class QueueCommandTest extends TestCase
{
    public function testOutputsQueueInfoAsText(): void
    {
        $client = new FakeEchoClient();
        $client->queueInfo = new QueueInfoResult(filesCount: 3, filesSize: 42, filesDuration: 9);

        $tester = new CommandTester(new QueueCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(
            implode("\n", [
                'files_count: 3',
                'files_size: 42 MB',
                'files_duration: 9 minutes',
                '',
            ]),
            $tester->getDisplay(true)
        );
    }

    public function testOutputsQueueInfoAsJson(): void
    {
        $client = new FakeEchoClient();
        $client->queueInfo = new QueueInfoResult(filesCount: 3, filesSize: 42, filesDuration: 9);

        $tester = new CommandTester(new QueueCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--json' => true]));
        $this->assertSame(
            [
                'files_count' => 3,
                'files_size' => 42,
                'files_duration' => 9,
            ],
            json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testWritesServiceFailureToStderr(): void
    {
        $client = new FakeEchoClient();
        $client->exception = new \RuntimeException('Service unavailable');

        $tester = new CommandTester(new QueueCommand(new FakeEchoClientFactory($client)));

        $this->assertSame(
            Command::FAILURE,
            $tester->execute([], ['capture_stderr_separately' => true])
        );
        $this->assertSame('', $tester->getDisplay(true));
        $this->assertStringContainsString('Error: Service unavailable', $tester->getErrorOutput(true));
    }
}
