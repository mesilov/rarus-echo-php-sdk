<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\ApplicationFactory;
use Rarus\Echo\Infrastructure\Console\Command\ExtractAudioCommand;
use Rarus\Echo\Infrastructure\Console\Media\AudioExtractor;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FailingEchoClientFactory;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeMediaPreparer;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class ExtractAudioCommandTest extends TestCase
{
    private string $directory;
    private string $source;
    private FakeMediaPreparer $preparer;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-extract-command-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->directory = (string) realpath($this->directory);
        $this->source = $this->directory . '/video.mp4';
        file_put_contents($this->source, 'video');
        $this->preparer = new FakeMediaPreparer();
        $path = $this->preparer->set->allocate('flac');
        file_put_contents($path, 'audio');
        $this->preparer->set->add($this->source, $path);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->preparer->set->cleanup();
        (new Filesystem())->remove($this->directory);
    }

    public function testTextAndJsonReturnOnlyOutputPathWhileProgressGoesToStderr(): void
    {
        foreach ([false, true] as $json) {
            $tester = new CommandTester(new ExtractAudioCommand(new AudioExtractor($this->preparer)));
            self::assertSame(0, $tester->execute(['file' => $this->source, '--json' => $json, '--audio-stream' => '2', '--media-timeout' => '42'], ['capture_stderr_separately' => true]));
            $path = $this->directory . DIRECTORY_SEPARATOR . 'video.flac';
            if ($json) {
                self::assertSame(['output' => $path], json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR));
            } else {
                self::assertSame($path . PHP_EOL, $tester->getDisplay());
            }
            self::assertStringContainsString('preparing:', $tester->getErrorOutput());
            self::assertNotNull($this->preparer->options);
            self::assertSame(2, $this->preparer->options->audioStream);
            self::assertSame(42, $this->preparer->options->timeout);
            unlink($path);
            $this->preparer = new FakeMediaPreparer();
            $prepared = $this->preparer->set->allocate('flac');
            file_put_contents($prepared, 'audio');
            $this->preparer->set->add($this->source, $prepared);
        }
    }

    public function testInvalidOptionsFailBeforePreparation(): void
    {
        foreach ([['--audio-stream' => '-1'], ['--media-timeout' => '0'], ['--media-timeout' => '1.5'], ['--media-timeout' => str_repeat('9', 30)]] as $options) {
            $tester = new CommandTester(new ExtractAudioCommand(new AudioExtractor($this->preparer)));
            self::assertSame(1, $tester->execute(array_merge(['file' => $this->source, '--json' => true], $options), ['capture_stderr_separately' => true]));
            self::assertSame('', $tester->getDisplay());
            self::assertStringContainsString('Error:', $tester->getErrorOutput());
            self::assertNull($this->preparer->options);
        }
    }

    public function testRegisteredCommandIgnoresMissingConnectionConfig(): void
    {
        $factory = new FailingEchoClientFactory();
        $application = ApplicationFactory::create($factory);
        $application->setAutoExit(false);
        self::assertTrue($application->has('extract-audio'));
        $tester = new ApplicationTester($application);
        self::assertSame(1, $tester->run(['command' => 'extract-audio', 'file' => '/missing/video.mp4', '--config' => '/missing/config.json', '--json' => true], ['capture_stderr_separately' => true]));
        self::assertSame(0, $factory->calls);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Media source', $tester->getErrorOutput());
    }

    public function testCancellationCancelsPreparerAndReturnsSignalExitCode(): void
    {
        $command = new ExtractAudioCommand(new AudioExtractor($this->preparer));
        $this->preparer->onPrepare = static function () use ($command): void {
            $command->handleSignal(2);
        };
        $tester = new CommandTester($command);
        self::assertSame(130, $tester->execute(['file' => $this->source], ['capture_stderr_separately' => true]));
        self::assertTrue($this->preparer->cancelled);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Signal', $tester->getErrorOutput());
        self::assertFileDoesNotExist($this->directory . '/video.flac');
    }
}
