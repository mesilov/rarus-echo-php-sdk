<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
final readonly class PreparationPolicy
{
    /** @param list<string> $copyRoutes */
    public function __construct(private bool $verifiedFlac, private array $copyRoutes)
    {
    }

    public static function committed(): self
    {
        $path = __DIR__ . '/compatibility.json';
        if (!is_file($path)) {
            return new self(false, []);
        }
        $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema_version'] ?? null) !== 1 || !is_bool($data['verified_flac'] ?? null) || !is_array($data['copy_routes'] ?? null)) {
            throw new \RuntimeException('Invalid Echo compatibility evidence.');
        }
        foreach ($data['copy_routes'] as $route) {
            if (!is_string($route) || !in_array($route, ['aac', 'opus', 'vorbis', 'mp3', 'flac'], true)) {
                throw new \RuntimeException('Invalid Echo compatibility route.');
            }
        }

        return new self($data['verified_flac'], array_values($data['copy_routes']));
    }

    /** @param array<string, mixed> $stream */
    public function plan(array $stream, float $origin): PreparationPlan
    {
        if (!$this->verifiedFlac) {
            throw new \RuntimeException('Automatic preparation is blocked: verified Echo compatibility for lossless audio and timelines is required.');
        }
        $start = $stream['start_time'] ?? null;
        $duration = $stream['duration'] ?? null;
        $rate = $stream['sample_rate'] ?? null;
        $channels = $stream['channels'] ?? null;
        if (!is_numeric($start) || !is_numeric($duration) || !is_numeric($rate) || !is_int($channels)
            || !is_finite((float) $start) || !is_finite((float) $duration) || (float) $duration <= 0
            || (int) $rate < 1 || (int) $rate > 655350 || $channels < 1 || $channels > 8) {
            throw new \RuntimeException('Missing or unrepresentable audio timeline, sample rate or channel count.');
        }
        $offset = (float) $start - $origin;
        if ($offset < -0.000001 || $offset > 86400 || (float) $duration > 86400) {
            throw new \RuntimeException('Unrepresentable audio timeline: audio begins before playback origin or exceeds supported duration.');
        }
        $samples = (int) round(max(0, $offset) * (int) $rate);
        $codec = $stream['codec_name'] ?? '';
        $extensions = ['aac' => 'm4a', 'opus' => 'ogg', 'vorbis' => 'ogg', 'mp3' => 'mp3', 'flac' => 'flac'];
        if ($samples === 0 && is_string($codec) && in_array($codec, $this->copyRoutes, true) && isset($extensions[$codec])) {
            return new PreparationPlan($extensions[$codec], $codec, ['-c:a', 'copy'], (float) $duration);
        }
        $filter = 'asetpts=PTS-STARTPTS';
        if ($samples > 0) {
            $filter .= ',adelay=' . $samples . 'S:all=1';
        }

        return new PreparationPlan('flac', 'flac', ['-c:a', 'flac', '-af', $filter, '-ar', (string) $rate, '-ac', (string) $channels], (float) $duration + $samples / (int) $rate);
    }
}
