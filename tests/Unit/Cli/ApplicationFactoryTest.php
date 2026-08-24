<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Cli\ApplicationFactory;
use Rarus\Echo\Tests\Unit\Cli\Support\FailingEchoClientFactory;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClient;
use Rarus\Echo\Tests\Unit\Cli\Support\FakeEchoClientFactory;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ApplicationFactoryTest extends TestCase
{
    public function testCreatesApplicationWithServiceCommands(): void
    {
        $application = ApplicationFactory::create(new FakeEchoClientFactory(new FakeEchoClient()));

        $this->assertTrue($application->has('queue'));
        $this->assertTrue($application->has('status'));
        $this->assertTrue($application->has('transcript'));
        $this->assertTrue($application->has('submit'));
    }

    public function testTopLevelHelpDoesNotCreateClient(): void
    {
        $factory = new FailingEchoClientFactory();
        $application = ApplicationFactory::create($factory);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $exitCode = $tester->run(['--help' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('List commands', $tester->getDisplay());
        $this->assertSame(0, $factory->calls);
    }
}
