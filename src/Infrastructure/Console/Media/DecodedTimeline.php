<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal Decoded samples account for codec priming and detect unsafe timestamp gaps. */
final readonly class DecodedTimeline
{
    private function __construct(public float $start, public int $samples, public float $duration)
    {
    }

    public static function fromCompactFile(string $path, int $sampleRate): self
    {
        $handle = fopen($path, 'rb');
        if ($handle === false || $sampleRate < 1) {
            throw new \RuntimeException('Cannot read decoded media timeline.');
        }
        $start = null;
        $samples = 0;
        $count = 0;

        try {
            while (($line = fgets($handle, 65537)) !== false) {
                if (strlen($line) > 65535 || ++$count > 1000000) {
                    throw new \RuntimeException('Decoded timeline frame limit exceeded.');
                }
                $fields = [];
                foreach (explode('|', trim($line)) as $field) {
                    $pair = explode('=', $field, 2);
                    if (count($pair) === 2) {
                        $fields[$pair[0]] = $pair[1];
                    }
                }
                if (!isset($fields['nb_samples']) && trim($line) === '') {
                    continue;
                }
                $timestamp = $fields['best_effort_timestamp_time'] ?? null;
                $frameSamples = filter_var($fields['nb_samples'] ?? null, FILTER_VALIDATE_INT);
                if (!is_numeric($timestamp) || !is_finite((float) $timestamp) || $frameSamples === false || $frameSamples < 1 || $frameSamples > 1048576) {
                    throw new \RuntimeException('Missing decoded audio timeline.');
                }
                $start ??= (float) $timestamp;
                if (abs((float) $timestamp - ($start + $samples / $sampleRate)) > max(0.002, 2 / $sampleRate)) {
                    throw new \RuntimeException('Ambiguous discontinuous decoded audio timeline.');
                }
                $samples += $frameSamples;
            }
            if (!feof($handle) || $start === null) {
                throw new \RuntimeException('Incomplete decoded audio timeline.');
            }
        } finally {
            fclose($handle);
        }

        return new self($start, $samples, $samples / $sampleRate);
    }
}
