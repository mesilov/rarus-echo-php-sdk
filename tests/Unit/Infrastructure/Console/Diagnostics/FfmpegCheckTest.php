<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Diagnostics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Diagnostics\FfmpegCheck;
use Rarus\Echo\Infrastructure\Console\Media\Toolkit;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitLocator;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitUnavailableException;
use Symfony\Component\Process\Process;

final class FfmpegCheckTest extends TestCase
{
    public function testMissingExecutableExplainsHowToEnableVideoAudioExtraction(): void
    {
        $result = (new FfmpegCheck(static function (float $deadline): Toolkit {
            throw new ToolkitUnavailableException('RecognizableSecret');
        }))->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_missing', $result->code);
        self::assertSame('Media toolkit was not found. Restore the complete CLI package, or configure both absolute RARUS_ECHO_FFMPEG_PATH and RARUS_ECHO_FFPROBE_PATH values to extract audio from video files.', $result->message);
        self::assertSame([], $result->details);
    }

    /** @return iterable<string, array{string, string}> */
    public static function versionLines(): iterable
    {
        yield 'release build' => ['n7.1.1-3+build', "ffmpeg version n7.1.1-3+build Copyright RecognizableSecret\nconfiguration: RecognizableSecret\n"];
        yield 'release with CRLF' => ['8.0.1', "ffmpeg version 8.0.1\r\nRecognizableSecret"];
        yield 'git revision' => ['N-117652-g2abc6789f3', "ffmpeg version N-117652-g2abc6789f3 Copyright RecognizableSecret\n"];
        yield 'dated git build' => ['2026-01-01-git-abcd-full_build', 'ffmpeg version 2026-01-01-git-abcd-full_build'];
    }

    #[DataProvider('versionLines')]
    public function testExecutableReportsOnlyVersionTokenAndResolvedPath(string $version, string $output): void
    {
        $process = new Process([PHP_BINARY, '-r', 'echo ' . var_export($output, true) . ';']);
        $check = new FfmpegCheck($this->resolver(), static function (string $path) use ($process): Process {
            self::assertSame('/fixture/ffmpeg', $path);

            return $process;
        });

        $result = $check->run();

        self::assertSame('ok', $result->status);
        self::assertSame('ffmpeg_ok', $result->code);
        self::assertSame(['path' => '/fixture/ffmpeg', 'version' => $version], $result->details);
        self::assertStringContainsString('extract audio from video files', $result->message);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testUnsuccessfulExecutableProducesSafeActionableError(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'echo "ffmpeg version 7.1.1\\nRecognizableSecret"; fwrite(STDERR, "RecognizableSecret"); exit(1);']);

        $result = $this->checkWithProcess($process)->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_failed', $result->code);
        self::assertSame('Media toolkit could not run successfully. Restore a matching complete CLI package or verify both absolute media overrides, their profile and executable permissions to extract audio from video files.', $result->message);
        self::assertSame([], $result->details);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidOutput(): iterable
    {
        yield 'empty' => [''];
        yield 'unrelated' => ['RecognizableSecret'];
        yield 'version on later line' => ["RecognizableSecret\nffmpeg version 7.1.1\n"];
        yield 'missing token' => ["ffmpeg version \nRecognizableSecret"];
        yield 'url token' => ["ffmpeg version https://RecognizableSecret.example/\n"];
        yield 'control sequence' => ["ffmpeg version 7.1.1\033[31mRecognizableSecret\n"];
        yield 'non ascii token' => ["ffmpeg version 7.1.1-Версия\n"];
        yield 'long token' => ['ffmpeg version 7.1.1-' . str_repeat('a', 128) . "\nRecognizableSecret"];
        yield 'credential uuid' => ["ffmpeg version 11111111-1111-4111-8111-111111111111\nRecognizableSecret"];
    }

    #[DataProvider('invalidOutput')]
    public function testUnrecognizableVersionProducesSafeActionableError(string $output): void
    {
        $process = new Process([PHP_BINARY, '-r', 'echo ' . var_export($output, true) . ';']);

        $result = $this->checkWithProcess($process)->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_invalid_version', $result->code);
        self::assertSame('ffmpeg did not return a recognizable version. Restore the matching media toolkit to extract audio from video files.', $result->message);
        self::assertSame([], $result->details);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testLaunchFailureDoesNotExposeExceptionDetails(): void
    {
        $check = new FfmpegCheck($this->resolver(), static function (string $path): Process {
            throw new \RuntimeException('RecognizableSecret at ' . $path);
        });

        $result = $check->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_failed', $result->code);
        self::assertSame([], $result->details);
        self::assertStringContainsString('executable permissions', $result->message);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testTimeoutProducesSafeActionableError(): void
    {
        $process = new Process([PHP_BINARY, '-r', 'echo "RecognizableSecret"; usleep(500000);'], timeout: 0.05);

        $result = $this->checkWithProcess($process)->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_timeout', $result->code);
        self::assertSame('Media toolkit readiness check timed out after five seconds. Check the matching toolkit to extract audio from video files.', $result->message);
        self::assertSame([], $result->details);
    }

    public function testDiscoveryFailureDoesNotExposeExceptionDetails(): void
    {
        $result = (new FfmpegCheck(static function (float $deadline): Toolkit {
            throw new \RuntimeException('RecognizableSecret');
        }))->run();

        self::assertSame('error', $result->status);
        self::assertSame('ffmpeg_failed', $result->code);
        self::assertSame([], $result->details);
        self::assertStringNotContainsString('RecognizableSecret', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    private function checkWithProcess(Process $process): FfmpegCheck
    {

        return new FfmpegCheck($this->resolver(), static fn (string $path): Process => $process);
    }

    /** @return \Closure(float): Toolkit */
    private function resolver(): \Closure
    {
        return static fn (float $deadline): Toolkit => new Toolkit('/fixture/ffmpeg', '/fixture/ffprobe');
    }

    public function testInvalidExplicitPairIsNotReplacedByPathDiscovery(): void
    {
        $check = new FfmpegCheck(static fn (float $deadline): Toolkit => (new ToolkitLocator(environment: ['RARUS_ECHO_FFMPEG_PATH' => '/fixture/ffmpeg']))->locate($deadline));
        $result = $check->run();
        self::assertSame('ffmpeg_failed', $result->code);
        self::assertSame([], $result->details);
    }

    public function testInjectedVersionProcessDoesNotInheritEchoCredentials(): void
    {
        putenv('RARUS_ECHO_DIAGNOSTIC_SECRET=RecognizableSecret');

        try {
            $process = new Process([PHP_BINARY, '-r', 'echo "ffmpeg version ".(getenv("RARUS_ECHO_DIAGNOSTIC_SECRET") === false ? "9.0.2" : "RecognizableSecret");'], env: ['RARUS_ECHO_EXTRA_SECRET' => 'RecognizableSecret']);
            $result = $this->checkWithProcess($process)->run();
            self::assertSame('ffmpeg_ok', $result->code);
            self::assertSame('9.0.2', $result->details['version']);
            self::assertSame(false, $process->getEnv()['RARUS_ECHO_EXTRA_SECRET']);
        } finally {
            putenv('RARUS_ECHO_DIAGNOSTIC_SECRET');
        }
    }

    public function testToolkitResolutionAndVersionProbeShareFiveSecondBudget(): void
    {
        $start = microtime(true);
        $process = new Process([PHP_BINARY, '-r', 'echo "ffmpeg version 9.0.2";'], timeout: null);
        $resolver = static function (float $deadline) use ($start): Toolkit {
            self::assertGreaterThan($start + 4.9, $deadline);
            self::assertLessThan($start + 5.1, $deadline);
            usleep(50000);

            return new Toolkit('/fixture/ffmpeg', '/fixture/ffprobe');
        };
        $result = (new FfmpegCheck($resolver, static fn (string $path): Process => $process))->run();
        self::assertSame('ffmpeg_ok', $result->code);
        self::assertGreaterThan(0, $process->getTimeout());
        self::assertLessThan(4.99, $process->getTimeout());
    }
}
