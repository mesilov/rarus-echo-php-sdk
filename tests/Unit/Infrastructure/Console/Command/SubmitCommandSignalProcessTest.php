<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Command;

use PHPUnit\Framework\TestCase;

final class SubmitCommandSignalProcessTest extends TestCase
{
    public function testSubmitWaitStopsWithShutdownMessageWhenInterruptedDuringPolling(): void
    {
        if (!\defined('SIGINT') || !\defined('SIGKILL')) {
            self::markTestSkipped('PCNTL signal constants are unavailable.');
        }

        if (!\function_exists('pcntl_signal') || !\function_exists('proc_open') || !\function_exists('proc_terminate')) {
            self::markTestSkipped('Process signal handling is unavailable.');
        }

        $root = dirname(__DIR__, 5);
        $scriptPath = sys_get_temp_dir() . '/rarus-echo-signal-test-' . bin2hex(random_bytes(4)) . '.php';
        self::assertNotFalse(file_put_contents($scriptPath, $this->signalAwareCliScript($root)));

        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                $scriptPath,
                'submit',
                'audio.ogg',
                '--wait',
                '--poll-interval=30',
                '--timeout=120',
            ],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root
        );

        self::assertIsResource($process);
        self::assertCount(2, $pipes);

        $stdout = '';
        $stderr = '';

        try {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $this->waitForOutput($pipes, $stdout, $stderr, 'polling: attempt=1', 5.0);
            self::assertStringContainsString('submitted: 11111111-1111-1111-1111-111111111111', $stderr);
            self::assertStringContainsString('polling: attempt=1 file_id=11111111-1111-1111-1111-111111111111 status=waiting', $stderr);

            self::assertTrue(proc_terminate($process, \SIGINT));
            $exitCode = $this->waitForExit($process, $pipes, $stdout, $stderr, 5.0);

            self::assertSame('', $stdout);
            self::assertStringContainsString('Signal SIGINT received, shutting down.', $stderr);
            self::assertSame(128 + \SIGINT, $exitCode);
        } finally {
            foreach ($pipes as $pipe) {
                if (\is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, \SIGKILL);
            }

            proc_close($process);

            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    private function signalAwareCliScript(string $root): string
    {
        return sprintf(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                require %s . '/vendor/autoload.php';

                use Rarus\Echo\Enum\TaskType;
                use Rarus\Echo\Enum\TranscriptionStatus;
                use Rarus\Echo\Infrastructure\Console\ApplicationFactory;
                use Rarus\Echo\Services\Transcription\Result\FileItemTranscriptResult;
                use Rarus\Echo\Services\Transcription\Result\TranscriptSubmitResult;
                use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClient;
                use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeEchoClientFactory;
                use Symfony\Component\Uid\Uuid;

                $fileId = Uuid::fromString('11111111-1111-1111-1111-111111111111');
                $client = new FakeEchoClient();
                $client->submitResult = new TranscriptSubmitResult([$fileId]);
                $client->transcript = new FileItemTranscriptResult(
                    fileId: $fileId,
                    transcriptionStatus: TranscriptionStatus::WAITING,
                    taskType: TaskType::TRANSCRIPTION,
                    result: null
                );

                exit(ApplicationFactory::create(new FakeEchoClientFactory($client))->run());
                PHP,
            var_export($root, true)
        );
    }

    /**
     * @param array<int, resource> $pipes
     */
    private function waitForOutput(array $pipes, string &$stdout, string &$stderr, string $needle, float $timeoutSeconds): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $this->collectOutput($pipes, $stdout, $stderr);
            if (str_contains($stdout . $stderr, $needle)) {
                return;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        self::fail(sprintf('Timed out waiting for output "%s". STDERR: %s', $needle, $stderr));
    }

    /**
     * @param resource              $process
     * @param array<int, resource>  $pipes
     */
    private function waitForExit($process, array $pipes, string &$stdout, string &$stderr, float $timeoutSeconds): int
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $exitCode = null;

        do {
            $this->collectOutput($pipes, $stdout, $stderr);
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];

                break;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->collectOutput($pipes, $stdout, $stderr);

        if ($exitCode === null) {
            self::fail(sprintf('Timed out waiting for process exit. STDERR: %s', $stderr));
        }

        self::assertNotSame(-1, $exitCode, $stderr);
        self::assertIsInt($exitCode);

        return $exitCode;
    }

    /**
     * @param array<int, resource> $pipes
     */
    private function collectOutput(array $pipes, string &$stdout, string &$stderr): void
    {
        $stdoutChunk = stream_get_contents($pipes[1]);
        $stderrChunk = stream_get_contents($pipes[2]);

        if ($stdoutChunk !== false) {
            $stdout .= $stdoutChunk;
        }

        if ($stderrChunk !== false) {
            $stderr .= $stderrChunk;
        }
    }
}
