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
                '--no-extract-audio',
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

    public function testInterruptDuringPreparationStopsChildAndRemovesPartialAudio(): void
    {
        if (!defined('SIGINT') || !function_exists('pcntl_signal') || PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Unix process signal handling is unavailable.');
        }
        $root = dirname(__DIR__, 5);
        $directory = sys_get_temp_dir() . '/echo-preparation-signal-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $script = '#!' . PHP_BINARY . "\n<?php\n" . <<<'CODE'
            if (in_array('-version', $argv, true)) { echo basename($argv[0]).' version 9.0.2'; exit; }
            if (in_array('-protocols', $argv, true)) { echo "Input:\n file\n pipe\nOutput:\n file\n pipe\n"; exit; }
            foreach (['-decoders','-encoders','-demuxers','-muxers','-filters'] as $option) {
                if (in_array($option,$argv,true)) {
                    foreach (explode(' ', 'mov matroska avi mp3 ogg wav flac aac asf mpegts mpeg ipod ac3 eac3 opus vorbis alac wmav1 wmav2 pcm_s16le pcm_s24le pcm_s32le pcm_f32le asetpts adelay aresample aformat') as $name) echo " A..... $name fixture\n";
                    exit;
                }
            }
            if (in_array('-show_streams',$argv,true)) { echo '{"format":{"start_time":"0"},"streams":[{"index":0,"codec_type":"video"},{"index":1,"codec_type":"audio","codec_name":"aac","sample_rate":"48000","channels":2}]}'; exit; }
            if (in_array('-show_frames',$argv,true)) { echo 'best_effort_timestamp_time=0|nb_samples=48000'; exit; }
            $file=$argv[count($argv)-1];
            file_put_contents($file,'partial audio');
            file_put_contents(dirname($argv[0]).'/active.json',json_encode(['file'=>$file,'pid'=>getmypid()]));
            sleep(30);
            CODE;
        foreach (['ffmpeg', 'ffprobe'] as $name) {
            file_put_contents($directory . '/' . $name, $script);
            chmod($directory . '/' . $name, 0o700);
        }
        file_put_contents($directory . '/cli.php', $this->signalAwareCliScript($root));
        $pipes = [];
        $process = proc_open([PHP_BINARY, $directory . '/cli.php', 'submit', $root . '/tests/Fixtures/Media/video-aac.mp4'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, array_merge(getenv(), ['RARUS_ECHO_FFMPEG_PATH' => $directory . '/ffmpeg', 'RARUS_ECHO_FFPROBE_PATH' => $directory . '/ffprobe']));
        self::assertIsResource($process);
        $stdout = '';
        $stderr = '';

        try {
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $deadline = microtime(true) + 5;
            while (!is_file($directory . '/active.json') && microtime(true) < $deadline) {
                usleep(20000);
            }
            self::assertFileExists($directory . '/active.json');
            $active = json_decode((string) file_get_contents($directory . '/active.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($active);
            self::assertFileExists($active['file']);
            self::assertTrue(proc_terminate($process, SIGINT));
            $exit = $this->waitForExit($process, $pipes, $stdout, $stderr, 5);
            self::assertSame(130, $exit);
            self::assertSame('', $stdout);
            self::assertStringContainsString('Signal SIGINT received, shutting down.', $stderr);
            self::assertFileDoesNotExist($active['file']);
            self::assertDirectoryDoesNotExist(dirname((string) $active['file']));
            if (function_exists('posix_kill')) {
                self::assertFalse(@posix_kill($active['pid'], 0));
            }
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (proc_get_status($process)['running']) {
                proc_terminate($process, 9);
            }
            proc_close($process);
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
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
