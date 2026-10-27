<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Command\InstallCommand;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigPathResolver;
use Rarus\Echo\Infrastructure\Console\Configuration\ConnectionConfigStore;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallCommandTest extends TestCase
{
    private string $directory;
    private string $path;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-install-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->path = $this->directory . '/.config/rarus-echo/config.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    private function command(): InstallCommand
    {
        $command = new InstallCommand(paths: new ConfigPathResolver($this->directory, ['HOME' => $this->directory], 'Linux'));
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));

        return $command;
    }

    public function testInteractiveInstallWritesPrivateConfigWithoutEchoingCredentials(): void
    {
        $command = $this->command();
        $tester = new CommandTester($command);
        $tester->setInputs(['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://example.com']);
        self::assertSame(0, $tester->execute([], ['interactive' => true]));
        self::assertSame('https://example.com', (new ConnectionConfigStore())->load($this->path)->credentials()->getBaseUrl());
        self::assertStringNotContainsString('11111111-1111-4111-8111-111111111111', $tester->getDisplay());
        self::assertStringNotContainsString('22222222-2222-4222-8222-222222222222', $tester->getDisplay());
    }

    public function testSecretQuestionCannotFallBackToVisibleInput(): void
    {
        $command = $this->command();
        $helper = $this->createMock(QuestionHelper::class);
        $answers = ['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://example.com'];
        $helper->method('ask')->willReturnCallback(function ($input, $output, Question $question) use (&$answers): string {
            if (str_contains($question->getQuestion(), 'API key')) {
                self::assertTrue($question->isHidden());
                self::assertFalse($question->isHiddenFallback());
            }

            $answer = array_shift($answers);
            self::assertIsString($answer);

            return $answer;
        });
        $command->setHelperSet(new HelperSet(['question' => $helper]));
        self::assertSame(0, (new CommandTester($command))->execute([], ['interactive' => true]));
    }

    public function testNoninteractiveInstallFailsWithoutWriting(): void
    {
        $tester = new CommandTester($this->command());
        self::assertSame(2, $tester->execute([], ['interactive' => false]));
        self::assertFileDoesNotExist($this->path);
    }

    public function testInvalidSecretIsRetriedWithoutDisclosureAndDefaultUrlIsSaved(): void
    {
        $tester = new CommandTester($this->command());
        $tester->setInputs(['RecognizableFixtureSecret', '11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', '']);
        self::assertSame(0, $tester->execute([], ['interactive' => true]));
        self::assertStringNotContainsString('RecognizableFixtureSecret', $tester->getDisplay());
        self::assertSame('https://production-ai-ui-api.ai.rarus-cloud.ru', (new ConnectionConfigStore())->load($this->path)->credentials()->getBaseUrl());
    }

    public function testCancelledOverwritePreservesConfig(): void
    {
        $tester = new CommandTester($this->command());
        $tester->setInputs(['11111111-1111-4111-8111-111111111111', '22222222-2222-4222-8222-222222222222', 'https://example.com']);
        $tester->execute([], ['interactive' => true]);
        $original = file_get_contents($this->path);
        $cancelled = new CommandTester($this->command());
        $cancelled->setInputs(['n']);
        self::assertSame(0, $cancelled->execute([], ['interactive' => true]));
        self::assertSame($original, file_get_contents($this->path));
    }
}
