<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

use Rarus\Echo\Infrastructure\Filesystem\FileHelper;
use Rarus\Echo\Infrastructure\Filesystem\FileValidator;

/** @internal CLI-only batch preparation; the SDK upload contract remains unchanged. */
final readonly class MediaPreparer implements MediaPreparationInterface
{
    private ToolkitLocator $locator;
    private FileValidator $validator;

    public function __construct(
        ?ToolkitLocator $locator = null,
        private ?PreparationPolicy $policy = null,
        private MediaProcessRunner $runner = new MediaProcessRunner(),
        private FileHelper $files = new FileHelper()
    ) {
        $this->locator = $locator ?? new ToolkitLocator($runner);
        $this->validator = new FileValidator($files);
    }

    #[\Override]
    public function cancel(): void
    {
        $this->runner->cancel();

    }

    /** @param list<string> $files @param \Closure(string): void $progress */
    #[\Override]
    public function prepare(array $files, MediaOptions $options, \Closure $progress): PreparedFileSet
    {
        $set = new PreparedFileSet(files: $this->files);
        $tools = null;

        try {
            foreach ($files as $file) {
                if ($options->noExtractAudio) {
                    $set->add($file, $file);

                    continue;
                }
                $absolute = realpath($file);
                if ($absolute === false || !is_file($absolute) || !is_readable($absolute) || filesize($absolute) === 0) {
                    throw new \RuntimeException('Media source must be a readable non-empty regular file: ' . $file);
                }
                $mime = $this->files->getMimeType($absolute);
                if (in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/x-pn-wav', 'audio/ogg', 'audio/flac', 'audio/x-flac', 'audio/aac', 'audio/m4a', 'audio/x-m4a', 'audio/webm'], true)) {
                    if ($options->audioStream !== null) {
                        throw new \RuntimeException('--audio-stream requires an input with video.');
                    }
                    $this->validator->validate($absolute);
                    $set->add($file, $file);

                    continue;
                }
                $deadline = microtime(true) + $options->timeout;
                $tools ??= $this->locator->locate($deadline);
                $probe = $this->probe($tools, $absolute, $deadline, $set);
                if (!$probe->hasVideo()) {
                    if ($options->audioStream !== null) {
                        throw new \RuntimeException('--audio-stream requires an input with video.');
                    }
                    $probe->selectAudio(null);
                    $this->validator->validate($absolute);
                    $set->add($file, $file);

                    continue;
                }
                $stream = $probe->selectAudio($options->audioStream);
                $rate = filter_var($stream['sample_rate'] ?? null, FILTER_VALIDATE_INT);
                if ($rate === false || $rate < 1) {
                    throw new \RuntimeException('Missing audio sample rate.');
                }
                $timeline = $this->timeline($tools, $absolute, (int) $stream['index'], $rate, $deadline, $set);
                $stream['start_time'] = $timeline->start;
                $stream['duration'] = $timeline->duration;
                $plan = ($this->policy ?? PreparationPolicy::committed())->plan($stream, $probe->origin);
                $path = $set->allocate($plan->extension, $deadline);
                $set->checkBudget();
                $progress(sprintf('preparing: %s audio_stream=%d codec=%s', $file, $stream['index'], (string) ($stream['codec_name'] ?? 'unknown')));
                $this->runner->run(array_merge([$tools->ffmpeg, '-nostdin', '-hide_banner', '-loglevel', 'error', '-xerror', '-i', $absolute, '-map', '0:' . $stream['index'], '-vn', '-sn', '-dn', '-map_metadata', '-1', '-map_chapters', '-1'], $plan->arguments, ['-y', $path]), $deadline, monitor: $set->checkBudget(...));
                $output = $this->probe($tools, $path, $deadline, $set);
                $audio = $output->selectAudio(null);
                if ($output->hasVideo() || count($output->streams) !== 1 || ($audio['codec_name'] ?? null) !== $plan->codec || (int) ($audio['sample_rate'] ?? 0) !== $rate || ($audio['channels'] ?? null) !== ($stream['channels'] ?? null)) {
                    throw new \RuntimeException('Prepared audio failed codec, stream, sample-rate or channel validation.');
                }
                if ($plan->bitsPerSample !== null && (int) ($audio['bits_per_raw_sample'] ?? 0) !== $plan->bitsPerSample) {
                    throw new \RuntimeException('Prepared audio failed lossless sample precision validation.');
                }
                $outputTimeline = $this->timeline($tools, $path, (int) $audio['index'], $rate, $deadline, $set);
                if (abs($outputTimeline->start) > 0.05 || abs($outputTimeline->duration - $plan->duration) > 0.05) {
                    throw new \RuntimeException('Prepared audio is incomplete or changes decoded timeline alignment by more than 50 ms.');
                }
                $set->checkBudget();
                $this->validator->validate($path);
                $set->add($file, $path);
            }

            return $set;
        } catch (\Throwable $exception) {
            $set->cleanup($exception);

            throw $exception;
        }
    }

    private function probe(Toolkit $tools, string $path, float $deadline, PreparedFileSet $set): MediaProbe
    {
        return MediaProbe::fromJson($this->runner->run([$tools->ffprobe, '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $path], $deadline, monitor: $set->checkBudget(...)));
    }

    private function timeline(Toolkit $tools, string $path, int $index, int $rate, float $deadline, PreparedFileSet $set): DecodedTimeline
    {
        $timeline = null;
        $this->runner->run([$tools->ffprobe, '-v', 'error', '-select_streams', (string) $index, '-show_frames', '-show_entries', 'frame=best_effort_timestamp_time,nb_samples', '-of', 'compact=p=0', $path], $deadline, 64 * 1024 * 1024, $set->checkBudget(...), static function (string $output) use (&$timeline, $rate): void {
            $timeline = DecodedTimeline::fromCompactFile($output, $rate);
        });

        return $timeline ?? throw new \RuntimeException('Cannot measure decoded audio timeline.');
    }
}
