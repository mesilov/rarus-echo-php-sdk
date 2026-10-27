<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;
use Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner;

final class MediaProcessRunnerTest extends TestCase
{
    public function testLiteralArgumentsAndCredentialsAreNotInherited(): void
    {
        putenv('RARUS_ECHO_SECRET=never-forward');

        try {
            $argument = 'quote " spaces $() ; Юникод';
            $output = (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'echo json_encode([getenv("RARUS_ECHO_SECRET"), $argv[1]]);', $argument], microtime(true) + 5);
            self::assertSame([false, $argument], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
        } finally {
            putenv('RARUS_ECHO_SECRET');
        }
    }

    public function testDeadlineTerminatesChildAndDoesNotReturnPartialOutput(): void
    {
        $started = microtime(true);

        try {
            (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'echo "partial"; sleep(5);'], $started + 0.15);
            self::fail('Expected deadline failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('timeout', $exception->getMessage());
            self::assertLessThan(3, microtime(true) - $started);
        }
    }

    public function testOutputOverflowIsFailureInsteadOfTruncation(): void
    {
        $this->expectExceptionMessage('output limit');
        (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'echo str_repeat("a", 200000);'], microtime(true) + 5, 1000);
    }

    public function testProcessFailureReportsOnlyBoundedDiagnostics(): void
    {
        $this->expectExceptionMessage('media fixture failed');
        (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'fwrite(STDERR, "media fixture failed"); exit(4);'], microtime(true) + 5);
    }

    public function testSuccessfulExitWithDecodeErrorsDoesNotAcceptIncompleteMedia(): void
    {
        $this->expectExceptionMessage('corrupt packet');
        (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'fwrite(STDERR, "corrupt packet");'], microtime(true) + 5);
    }

    public function testProtectionFailureRunsNoChildAndRemovesProcessDirectory(): void
    {
        $protection = new class () extends ConfigFileProtection {
            public ?string $path = null;

            public function protect(string $path): void
            {
                $this->path = $path;

                throw new \RuntimeException('ACL fixture refused');
            }
        };

        try {
            (new MediaProcessRunner($protection))->run([PHP_BINARY, '-r', 'exit(0);'], microtime(true) + 5);
            self::fail('Expected protection failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('ACL fixture refused', $exception->getMessage());
            self::assertNotNull($protection->path);
            self::assertDirectoryDoesNotExist($protection->path);
        }
    }

    public function testOutputValidationCannotReturnSuccessAfterCombinedDeadline(): void
    {
        $this->expectExceptionMessage('timeout');
        (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'echo "complete";'], microtime(true) + 0.1, consume: static function (string $path): void {
            usleep(150000);
        });
    }

    public function testCleanupFailurePreservesPrimaryErrorAndReportsCleanupDiagnostic(): void
    {
        $primary = new \RuntimeException('primary protection failure');
        $protection = new class ($primary) extends ConfigFileProtection {
            public ?string $path = null;

            public function __construct(private readonly \RuntimeException $primary)
            {
                parent::__construct();
            }

            public function protect(string $path): void
            {
                $this->path = $path;
                parent::protect($path);
                file_put_contents($path . '/unexpected-fixture', 'test-owned leftover');

                throw $this->primary;
            }
        };
        $log = tempnam(sys_get_temp_dir(), 'echo-cleanup-diagnostic-');
        self::assertIsString($log);
        $previousLog = ini_set('error_log', $log);

        try {
            try {
                (new MediaProcessRunner($protection))->run([PHP_BINARY, '-r', 'exit(0);'], microtime(true) + 5);
                self::fail('Expected primary failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame($primary, $exception);
            }
            self::assertStringContainsString('Cannot remove media process temporary storage', (string) file_get_contents($log));
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
            if ($protection->path !== null) {
                unlink($protection->path . '/unexpected-fixture');
                rmdir($protection->path);
            }
            unlink($log);
        }
    }

    public function testCleanupFailureStillRejectsOtherwiseSuccessfulProcess(): void
    {
        $directory = null;

        try {
            $this->expectExceptionMessage('Cannot remove media process temporary storage');
            (new MediaProcessRunner())->run([PHP_BINARY, '-r', 'echo "complete";'], microtime(true) + 5, consume: static function (string $path) use (&$directory): void {
                $directory = dirname($path);
                file_put_contents($directory . '/unexpected-fixture', 'test-owned leftover');
            });
        } finally {
            if ($directory !== null) {
                self::assertFileDoesNotExist($directory . '/stdout');
                self::assertFileDoesNotExist($directory . '/stderr');
                unlink($directory . '/unexpected-fixture');
                rmdir($directory);
            }
        }
    }
}
