<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal Conservative metadata inspection, not a media decoder or a replacement for ffprobe. */
final class WebmAudioInspector
{
    private const int MAX_METADATA_BYTES = 1024 * 1024;
    private int $remainingBytes = self::MAX_METADATA_BYTES;
    private int $remainingElements = 4096;

    /** @param resource $stream */
    private function __construct(private readonly mixed $stream, private readonly int $length)
    {
    }

    public static function isAudioOnly(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            $stat = fstat($stream);

            return $stat !== false && (new self($stream, $stat['size']))->inspect();
        } finally {
            fclose($stream);
        }
    }

    private function inspect(): bool
    {
        $header = $this->element(0, $this->length);
        if ($header === null || $header['id'] !== 0x1A45DFA3 || $header['end'] > 4096 || !$this->webmHeader($header)) {
            return false;
        }
        $segment = $this->element($header['end'], $this->length, allowUnknownSegment: true);
        if ($segment === null || $segment['id'] !== 0x18538067 || $segment['end'] !== $this->length) {
            return false;
        }
        $children = $this->children($segment);
        if ($children === null) {
            return false;
        }
        $hasTracks = false;
        $hasInfo = false;
        $hasCluster = false;
        foreach ($children as $child) {
            if ($child['id'] === 0x1654AE6B) {
                if ($hasTracks || $child['end'] - $child['start'] > self::MAX_METADATA_BYTES || !$this->audioTracks($child)) {
                    return false;
                }
                $hasTracks = true;
            } elseif (!in_array($child['id'], [0x114D9B74, 0x1549A966, 0x1F43B675, 0x1C53BB6B, 0x1941A469, 0x1043A770, 0x1254C367, 0xEC, 0xBF], true)) {
                return false;
            }
            $hasInfo = $hasInfo || $child['id'] === 0x1549A966;
            $hasCluster = $hasCluster || $child['id'] === 0x1F43B675;
        }

        return $hasTracks && $hasInfo && $hasCluster;
    }

    /** @param array{id: int, start: int, end: int} $header */
    private function webmHeader(array $header): bool
    {
        $children = $this->children($header);
        if ($children === null) {
            return false;
        }
        $seen = [];
        foreach ($children as $child) {
            $id = $child['id'];
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            if ($id === 0x4282) {
                if ($child['end'] - $child['start'] !== 4 || $this->read($child['start'], 4) !== 'webm') {
                    return false;
                }
            } else {
                $value = $this->unsigned($child);
                $valid = match ($id) {
                    0x4286, 0x42F7 => $value === 1,
                    0x42F2 => $value === 4,
                    0x42F3 => $value !== null && $value >= 1 && $value <= 8,
                    0x4287, 0x4285 => $value !== null && $value >= 1 && $value <= 4,
                    default => false,
                };
                if (!$valid) {
                    return false;
                }
            }
        }

        return isset($seen[0x4282]);
    }

    /** @param array{id: int, start: int, end: int} $tracks */
    private function audioTracks(array $tracks): bool
    {
        $entries = $this->children($tracks);
        if ($entries === null) {
            return false;
        }
        $hasAudio = false;
        foreach ($entries as $entry) {
            if (in_array($entry['id'], [0xEC, 0xBF], true)) {
                continue;
            }
            if ($entry['id'] !== 0xAE || !$this->audioEntry($entry)) {
                return false;
            }
            $hasAudio = true;
        }

        return $hasAudio;
    }

    /** @param array{id: int, start: int, end: int} $entry */
    private function audioEntry(array $entry): bool
    {
        $fields = $this->children($entry);
        if ($fields === null) {
            return false;
        }
        $seen = [];
        foreach ($fields as $field) {
            $id = $field['id'];
            if (in_array($id, [0xEC, 0xBF], true)) {
                continue;
            }
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            $valid = match ($id) {
                0x83 => $this->unsigned($field) === 2,
                0xD7 => ($this->unsigned($field) ?? 0) > 0,
                0x73C5 => $field['end'] - $field['start'] >= 1 && $field['end'] - $field['start'] <= 8 && trim($this->read($field['start'], $field['end'] - $field['start']) ?? '', "\0") !== '',
                0x86 => $field['end'] - $field['start'] <= 8 && in_array($this->read($field['start'], $field['end'] - $field['start']), ['A_OPUS', 'A_VORBIS'], true),
                0xE1 => $this->audioSettings($field),
                // Known finite metadata cannot introduce another track or video settings.
                0x9C, 0x88, 0xB9, 0x55AA, 0x55AB, 0x55AC, 0x55AD, 0x55AE, 0x55AF, 0x22B59C, 0x22B59D, 0x536E, 0x23E383, 0x56AA, 0x56BB, 0x55EE, 0x63A2 => true,
                default => false,
            };
            if (!$valid) {
                return false;
            }
        }

        return isset($seen[0xD7], $seen[0x73C5], $seen[0x83], $seen[0x86], $seen[0xE1]);
    }

    /** @param array{id: int, start: int, end: int} $audio */
    private function audioSettings(array $audio): bool
    {
        $fields = $this->children($audio);
        if ($fields === null) {
            return false;
        }
        foreach ($fields as $field) {
            $valid = match ($field['id']) {
                0xB5, 0x78B5 => in_array($field['end'] - $field['start'], [4, 8], true),
                0x9F, 0x6264 => ($this->unsigned($field) ?? 0) > 0,
                0xEC, 0xBF => true,
                default => false,
            };
            if (!$valid) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{id: int, start: int, end: int} $parent
     * @return null|list<array{id: int, start: int, end: int}>
     */
    private function children(array $parent): ?array
    {
        $elements = [];
        for ($offset = $parent['start']; $offset < $parent['end']; $offset = $child['end']) {
            $child = $this->element($offset, $parent['end']);
            if ($child === null) {
                return null;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    /** @return null|array{id: int, start: int, end: int} */
    private function element(int $offset, int $end, bool $allowUnknownSegment = false): ?array
    {
        if (--$this->remainingElements < 0 || $end - $offset < 2) {
            return null;
        }
        $bytes = $this->read($offset, min(12, $end - $offset));
        if ($bytes === null) {
            return null;
        }
        $cursor = 0;
        $id = $this->vint($bytes, $cursor, 4, true);
        $size = $this->vint($bytes, $cursor, 8, false);
        if ($id === null || $size === null) {
            return null;
        }
        // Only the root Segment may use the regular file's EOF as its bound.
        if ($size === -1 && $allowUnknownSegment && $id === 0x18538067) {
            $size = $end - $offset - $cursor;
        }
        if ($size < 0 || $size > $end - $offset - $cursor) {
            return null;
        }

        return ['id' => $id, 'start' => $offset + $cursor, 'end' => $offset + $cursor + $size];
    }

    /** Unknown data size returns -1; malformed or reserved identifiers return null. */
    private function vint(string $bytes, int &$cursor, int $maxWidth, bool $identifier): ?int
    {
        $first = ord($bytes[$cursor] ?? "\0");
        $marker = 0x80;
        $width = 1;
        while (($first & $marker) === 0 && $width <= $maxWidth) {
            $marker >>= 1;
            ++$width;
        }
        if ($width > $maxWidth || $cursor + $width > strlen($bytes)) {
            return null;
        }
        $value = $identifier ? $first : ($first & ($marker - 1));
        for ($index = 1; $index < $width; ++$index) {
            $value = ($value << 8) | ord($bytes[$cursor + $index]);
        }
        $cursor += $width;
        $data = $identifier ? $value ^ (1 << (7 * $width)) : $value;
        $unknown = $data === (1 << (7 * $width)) - 1;
        if ($identifier && ($unknown || $data === 0 || ($width > 1 && $data < (1 << (7 * ($width - 1))) - 1))) {
            return null;
        }

        return $unknown ? -1 : $value;
    }

    /** @param array{id: int, start: int, end: int} $element */
    private function unsigned(array $element): ?int
    {
        $length = $element['end'] - $element['start'];
        if ($length < 1 || $length > 4) {
            return null;
        }
        $bytes = $this->read($element['start'], $length);
        if ($bytes === null) {
            return null;
        }
        $value = 0;
        for ($index = 0; $index < $length; ++$index) {
            $value = ($value << 8) | ord($bytes[$index]);
        }

        return $value;
    }

    private function read(int $offset, int $length): ?string
    {
        if ($length < 1 || $length > $this->remainingBytes || fseek($this->stream, $offset) !== 0) {
            return null;
        }
        $this->remainingBytes -= $length;
        $bytes = fread($this->stream, $length);

        return $bytes !== false && strlen($bytes) === $length ? $bytes : null;
    }
}
