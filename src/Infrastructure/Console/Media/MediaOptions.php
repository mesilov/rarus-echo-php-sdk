<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal */
final readonly class MediaOptions
{
    public function __construct(public ?int $audioStream = null, public bool $noExtractAudio = false, public int $timeout = 600)
    {
    }

    public static function parse(mixed $stream, bool $bypass, mixed $timeout, int $fileCount): self
    {
        $index = $stream === null ? null : self::integer($stream, 'audio-stream', 0);
        if ($index !== null && ($bypass || $fileCount !== 1)) {
            throw new \InvalidArgumentException('--audio-stream requires one video input and cannot be used with --no-extract-audio.');
        }

        return new self($index, $bypass, self::integer($timeout, 'media-timeout', 1));
    }

    private static function integer(mixed $value, string $option, int $minimum): int
    {
        $text = is_scalar($value) ? (string) $value : '';
        $decimal = ltrim($text, '0');
        $decimal = $decimal === '' ? '0' : $decimal;
        $maximum = (string) PHP_INT_MAX;
        if (!ctype_digit($text) || strlen($decimal) > strlen($maximum)
            || (strlen($decimal) === strlen($maximum) && strcmp($decimal, $maximum) > 0)
            || (int) $decimal < $minimum) {
            throw new \InvalidArgumentException(sprintf('--%s must be a %s integer.', $option, $minimum === 0 ? 'non-negative' : 'positive'));
        }

        return (int) $decimal;
    }
}
