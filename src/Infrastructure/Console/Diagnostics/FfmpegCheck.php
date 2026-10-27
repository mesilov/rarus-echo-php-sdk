<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

use Closure;
use Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner;
use Rarus\Echo\Infrastructure\Console\Media\Toolkit;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitLocator;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitUnavailableException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/** @internal */
final readonly class FfmpegCheck
{
    /**
     * @param null|Closure(float): Toolkit $toolkitResolver
     * @param null|Closure(string): Process $processFactory
     */
    public function __construct(
        private ?Closure $toolkitResolver = null,
        private ?Closure $processFactory = null
    ) {
    }

    public function run(): CheckResult
    {
        $deadline = microtime(true) + 5.0;

        try {
            $toolkit = $this->toolkitResolver instanceof Closure
                ? ($this->toolkitResolver)($deadline)
                : (new ToolkitLocator())->locate($deadline);
            $path = $toolkit->ffmpeg;
            if (microtime(true) >= $deadline) {
                return $this->timedOut();
            }
            if ($this->processFactory instanceof Closure) {
                $process = ($this->processFactory)($path);
                $environment = $process->getEnv();
                foreach (array_keys([...getenv(), ...$_ENV, ...$_SERVER, ...$environment]) as $key) {
                    if (str_starts_with(strtoupper((string) $key), 'RARUS_ECHO_')) {
                        $environment[$key] = false;
                    }
                }
                $process->setEnv($environment);
                $process->setTimeout(min($process->getTimeout() ?? 5.0, max(0.001, $deadline - microtime(true))));
                $process->run();
                if (!$process->isSuccessful()) {
                    return $this->failed();
                }
                $versionOutput = $process->getOutput();
            } else {
                $versionOutput = (new MediaProcessRunner())->run([$path, '-version'], $deadline);
            }
            if (microtime(true) >= $deadline) {
                return $this->timedOut();
            }

            $firstLine = rtrim(explode("\n", $versionOutput, 2)[0], "\r");
            if (preg_match('/\Affmpeg version ([A-Za-z0-9][A-Za-z0-9._+~-]{0,127})(?:[ \t]|\z)/', $firstLine, $matches) !== 1
                || preg_match('/\A(?:[nN]?\d+\.\d+|N-\d+-g[0-9a-fA-F]+|\d{4}-\d{2}-\d{2}-git-)/', $matches[1]) !== 1) {
                return new CheckResult(
                    'error',
                    'ffmpeg_invalid_version',
                    'ffmpeg did not return a recognizable version. Restore the matching media toolkit to extract audio from video files.'
                );
            }
        } catch (ToolkitUnavailableException) {
            return new CheckResult(
                'error',
                'ffmpeg_missing',
                'Media toolkit was not found. Restore the complete CLI package, or configure both absolute RARUS_ECHO_FFMPEG_PATH and RARUS_ECHO_FFPROBE_PATH values to extract audio from video files.'
            );
        } catch (ProcessTimedOutException) {
            return $this->timedOut();
        } catch (\Throwable) {
            return microtime(true) >= $deadline ? $this->timedOut() : $this->failed();
        }

        return new CheckResult(
            'ok',
            'ffmpeg_ok',
            'ffmpeg is ready to extract audio from video files.',
            ['path' => $path, 'version' => $matches[1]]
        );
    }

    private function timedOut(): CheckResult
    {
        return new CheckResult(
            'error',
            'ffmpeg_timeout',
            'Media toolkit readiness check timed out after five seconds. Check the matching toolkit to extract audio from video files.'
        );
    }

    private function failed(): CheckResult
    {
        return new CheckResult(
            'error',
            'ffmpeg_failed',
            'Media toolkit could not run successfully. Restore a matching complete CLI package or verify both absolute media overrides, their profile and executable permissions to extract audio from video files.'
        );
    }
}
