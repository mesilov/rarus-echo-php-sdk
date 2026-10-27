<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
final readonly class MediaProbe
{
    /** @param list<array<string, mixed>> $streams */
    private function __construct(public array $streams, public float $origin)
    {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['streams']) || !is_array($data['streams']) || count($data['streams']) > 256) {
            throw new \RuntimeException('Invalid media probe streams.');
        }
        $streams = [];
        $indices = [];
        foreach ($data['streams'] as $stream) {
            if (!is_array($stream) || !is_int($stream['index'] ?? null) || $stream['index'] < 0 || isset($indices[$stream['index']])) {
                throw new \RuntimeException('Invalid media stream index.');
            }
            $indices[$stream['index']] = true;
            $streams[] = $stream;
        }
        $origin = $data['format']['start_time'] ?? null;
        $hasVideo = array_filter($streams, static fn (array $stream): bool => ($stream['codec_type'] ?? null) === 'video' && ($stream['disposition']['attached_pic'] ?? 0) !== 1) !== [];
        if ($origin === null && !$hasVideo) {
            $origin = 0;
        }
        if (!is_numeric($origin) || !is_finite((float) $origin)) {
            throw new \RuntimeException('Missing or ambiguous media timeline origin.');
        }

        return new self($streams, (float) $origin);
    }

    public function hasVideo(): bool
    {
        foreach ($this->streams as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && ($stream['disposition']['attached_pic'] ?? 0) !== 1) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function selectAudio(?int $index): array
    {
        if ($index !== null && !$this->hasVideo()) {
            throw new \RuntimeException('--audio-stream requires an input with video.');
        }
        $audio = array_values(array_filter($this->streams, static fn (array $stream): bool => ($stream['codec_type'] ?? null) === 'audio'));
        usort($audio, static fn (array $left, array $right): int => $left['index'] <=> $right['index']);
        if ($index !== null) {
            foreach ($audio as $stream) {
                if ($stream['index'] === $index) {
                    return $stream;
                }
            }

            throw new \RuntimeException('--audio-stream does not identify an audio stream.');
        }
        if ($audio === []) {
            throw new \RuntimeException('Input contains no audio stream.');
        }
        $defaults = array_values(array_filter($audio, static fn (array $stream): bool => ($stream['disposition']['default'] ?? 0) === 1));

        return count($defaults) === 1 ? $defaults[0] : $audio[0];
    }
}
