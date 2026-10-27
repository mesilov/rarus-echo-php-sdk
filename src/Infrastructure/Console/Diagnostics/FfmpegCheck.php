<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Diagnostics;

use Closure;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** @internal */
final readonly class FfmpegCheck
{
    /** @param null|Closure(string): Process $processFactory */
    public function __construct(
        private ExecutableFinder $finder = new ExecutableFinder(),
        private ?Closure $processFactory = null
    ) {
    }

    public function run(): CheckResult
    {
        try {
            $path = $this->finder->find('ffmpeg');
            if ($path === null) {
                return new CheckResult(
                    'error',
                    'ffmpeg_missing',
                    'ffmpeg was not found. Install ffmpeg and add it to PATH to extract audio from video files.'
                );
            }

            $process = $this->processFactory instanceof \Closure
                ? ($this->processFactory)($path)
                : new Process([$path, '-version'], timeout: 5.0);
            $process->run();
            if (!$process->isSuccessful()) {
                return $this->failed();
            }

            $firstLine = rtrim(explode("\n", $process->getOutput(), 2)[0], "\r");
            if (preg_match('/\Affmpeg version ([A-Za-z0-9][A-Za-z0-9._+~-]{0,127})(?:[ \t]|\z)/', $firstLine, $matches) !== 1
                || preg_match('/\A(?:[nN]?\d+\.\d+|N-\d+-g[0-9a-fA-F]+|\d{4}-\d{2}-\d{2}-git-)/', $matches[1]) !== 1) {
                return new CheckResult(
                    'error',
                    'ffmpeg_invalid_version',
                    'ffmpeg did not return a recognizable version. Reinstall a working ffmpeg to extract audio from video files.'
                );
            }
        } catch (ProcessTimedOutException) {
            return new CheckResult(
                'error',
                'ffmpeg_timeout',
                'ffmpeg version check timed out. Repair or reinstall ffmpeg to extract audio from video files.'
            );
        } catch (\Throwable) {
            return $this->failed();
        }

        return new CheckResult(
            'ok',
            'ffmpeg_ok',
            'ffmpeg is ready to extract audio from video files.',
            ['path' => $path, 'version' => $matches[1]]
        );
    }

    private function failed(): CheckResult
    {
        return new CheckResult(
            'error',
            'ffmpeg_failed',
            'ffmpeg could not run successfully. Check its installation and executable permissions to extract audio from video files.'
        );
    }
}
