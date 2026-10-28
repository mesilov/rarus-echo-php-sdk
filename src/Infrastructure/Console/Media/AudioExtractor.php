<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

use Symfony\Component\Filesystem\Filesystem;

/** @internal Retains verified CLI audio without resolving an Echo connection. */
final readonly class AudioExtractor
{
    public function __construct(
        private MediaPreparationInterface $preparer = new MediaPreparer(),
        private Filesystem $filesystem = new Filesystem()
    ) {
    }

    public function cancel(): void
    {
        $this->preparer->cancel();
    }

    /** @param \Closure(string): void $progress */
    public function extract(string $file, MediaOptions $options, \Closure $progress): string
    {
        $directory = realpath(dirname($file));
        if ($directory === false || !is_file($file) || !is_readable($file) || filesize($file) === 0) {
            throw new \RuntimeException('Media source must be a readable non-empty regular file: ' . $file);
        }
        $set = $this->preparer->prepare([$file], $options, $progress);
        $failure = null;
        $published = false;
        $destination = null;

        try {
            try {
                $path = $set->paths()[0];
                if (realpath($path) === realpath($file)) {
                    throw new \RuntimeException('extract-audio requires an input with video.');
                }
                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $destination = $directory . DIRECTORY_SEPARATOR . pathinfo($file, PATHINFO_FILENAME) . '.' . $extension;
                if (file_exists($destination) || is_link($destination)) {
                    throw new \RuntimeException('Audio output already exists: ' . $destination);
                }
                $staging = $directory . DIRECTORY_SEPARATOR . '.rarus-echo-audio-' . bin2hex(random_bytes(16));

                try {
                    $this->filesystem->mkdir($staging, 0o700);
                    $staged = $staging . DIRECTORY_SEPARATOR . 'audio.' . $extension;
                    $this->filesystem->copy($path, $staged);
                    // link() creates a new directory entry atomically and never replaces one.
                    // Both paths are on the destination filesystem, even when TEMP is elsewhere.
                    if (!@link($staged, $destination)) {
                        throw new \RuntimeException('Cannot publish audio output (destination exists or filesystem does not support hard links): ' . $destination);
                    }
                    $published = true;
                } finally {
                    $this->filesystem->remove($staging);
                }
            } catch (\Throwable $exception) {
                $failure = $exception;

                throw $exception;
            } finally {
                $set->cleanup($failure);
            }
        } catch (\Throwable $exception) {
            if ($published && $destination !== null) {
                $this->filesystem->remove($destination);
            }

            throw $exception;
        }

        return $destination;
    }
}
