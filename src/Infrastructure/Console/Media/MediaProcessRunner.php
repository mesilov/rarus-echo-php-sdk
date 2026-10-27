<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;

/** @internal Executes media tools without a shell or pipe buffering assumptions. */
final class MediaProcessRunner
{
    /** @var resource|null */
    private $active;
    private bool $cancelled = false;

    public function __construct(private readonly ?ConfigFileProtection $protection = null)
    {
    }

    public function cancel(): void
    {
        $this->cancelled = true;
        $this->stop();
    }

    /**
     * @param list<string> $arguments
     * @param null|\Closure(): void $monitor
     * @param null|\Closure(string): void $consume
     */
    public function run(array $arguments, float $deadline, int $outputLimit = 1048576, ?\Closure $monitor = null, ?\Closure $consume = null): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rarus-echo-process-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0o700)) {
            throw new \RuntimeException('Cannot create private media process directory.');
        }
        $stdout = $directory . DIRECTORY_SEPARATOR . 'stdout';
        $stderr = $directory . DIRECTORY_SEPARATOR . 'stderr';
        $environment = getenv();
        foreach (array_keys($environment) as $key) {
            if (str_starts_with(strtoupper($key), 'RARUS_ECHO_')) {
                unset($environment[$key]);
            }
        }
        $environment['AV_LOG_FORCE_NOCOLOR'] = '1';

        try {
            $this->check($deadline, $directory);
            ($this->protection ?? new ConfigFileProtection(min(10.0, max(0.001, $deadline - microtime(true)))))->protect($directory);
            $this->check($deadline, $directory);
            $pipes = [];
            $process = proc_open($arguments, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $stdout, 'w'], 2 => ['file', $stderr, 'w']], $pipes, null, $environment, ['bypass_shell' => true, 'create_process_group' => true]);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot start media tool.');
            }
            $this->active = $process;
            do {
                $this->check($deadline, $directory);
                $monitor?->__invoke();
                $this->check($deadline, $directory);
                clearstatcache(true, $stdout);
                clearstatcache(true, $stderr);
                if ((is_file($stdout) && filesize($stdout) > $outputLimit) || (is_file($stderr) && filesize($stderr) > 65536)) {
                    throw new \RuntimeException('Media tool output limit exceeded.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(20000);
            } while (true);
            $exitCode = $status['exitcode'];
            proc_close($process);
            $this->active = null;
            $this->check($deadline, $directory);
            $monitor?->__invoke();
            clearstatcache(true, $stdout);
            clearstatcache(true, $stderr);
            if (filesize($stdout) > $outputLimit || filesize($stderr) > 65536) {
                throw new \RuntimeException('Media tool output limit exceeded.');
            }
            if ($exitCode !== 0 || filesize($stderr) > 0) {
                $diagnostic = (string) file_get_contents($stderr, false, null, 0, 4096);
                $diagnostic = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '', $diagnostic) ?? '';

                throw new \RuntimeException('Media tool failed: ' . trim($diagnostic));
            }

            if ($consume instanceof \Closure) {
                $consume($stdout);
                $this->check($deadline, $directory);
                $monitor?->__invoke();

                $result = '';
            } else {
                $result = (string) file_get_contents($stdout);
            }
        } catch (\Throwable $exception) {
            $this->cleanup($stdout, $stderr, $directory, $exception);

            throw $exception;
        }
        $this->cleanup($stdout, $stderr, $directory);

        return $result;
    }

    private function cleanup(string $stdout, string $stderr, string $directory, ?\Throwable $primaryError = null): void
    {
        $cleanupError = null;

        try {
            $this->stop();
        } catch (\Throwable $exception) {
            $cleanupError = $exception;
        }
        // Attempt every owned path even if stopping the child or removing another path failed.
        $cleaned = [$this->removeOwnedPath($stdout), $this->removeOwnedPath($stderr), $this->removeOwnedPath($directory, true)];
        if (in_array(false, $cleaned, true)) {
            $cleanupError ??= new \RuntimeException('Cannot remove media process temporary storage after closing child handles: ' . $directory);
        }
        if ($cleanupError instanceof \Throwable) {
            if ($primaryError instanceof \Throwable) {
                error_log('Media cleanup also failed: ' . $cleanupError->getMessage());

                return;
            }

            throw $cleanupError;
        }
    }

    private function removeOwnedPath(string $path, bool $directory = false): bool
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            clearstatcache(true, $path);
            if (!file_exists($path) || ($directory ? @rmdir($path) : @unlink($path))) {
                return true;
            }
            usleep(20000);
        }

        return false;
    }

    private function check(float $deadline, string $directory): void
    {
        if ($this->cancelled) {
            throw new \RuntimeException('Media preparation cancelled.');
        }
        if (microtime(true) >= $deadline) {
            throw new \RuntimeException('Media preparation timeout exceeded.');
        }
        $available = disk_free_space($directory);
        if ($available === false || $available < 1024 * 1024) {
            throw new \RuntimeException('Insufficient available space for media process temporary storage.');
        }
    }

    private function stop(): void
    {
        if (!is_resource($this->active)) {
            return;
        }
        $status = proc_get_status($this->active);
        if ($status['running']) {
            if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_generate_ctrl_event')) {
                @sapi_windows_generate_ctrl_event(1, $status['pid']);
            }
            @proc_terminate($this->active);
            $deadline = microtime(true) + 1;
            while (proc_get_status($this->active)['running'] && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (proc_get_status($this->active)['running']) {
                @proc_terminate($this->active, 9);
            }
        }
        proc_close($this->active);
        $this->active = null;
    }
}
