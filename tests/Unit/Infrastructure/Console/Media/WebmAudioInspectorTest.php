<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\WebmAudioInspector;

final class WebmAudioInspectorTest extends TestCase
{
    public function testActualWebmTrackMetadataDistinguishesAudioFromVideo(): void
    {
        self::assertTrue(WebmAudioInspector::isAudioOnly(dirname(__DIR__, 4) . '/Fixtures/Media/audio-only.webm'));
        self::assertFalse(WebmAudioInspector::isAudioOnly(dirname(__DIR__, 4) . '/Fixtures/Media/video-opus.webm'));
    }

    public function testBinaryPayloadIsNotSearchedForTrackMarkers(): void
    {
        $entry = self::audioFields() . self::element('63A2', hex2bin('838101') . 'V_VP9');
        $path = $this->temporaryFile(self::webm(self::element('AE', $entry)));

        try {
            self::assertTrue(WebmAudioInspector::isAudioOnly($path));
        } finally {
            unlink($path);
        }
    }

    public function testUnknownSizedRootSegmentDistinguishesActualAudioAndVideo(): void
    {
        foreach (['audio-only.webm' => true, 'video-opus.webm' => false] as $fixture => $expected) {
            $bytes = file_get_contents(dirname(__DIR__, 4) . '/Fixtures/Media/' . $fixture);
            self::assertIsString($bytes);
            self::assertSame("\x18\x53\x80\x67\x01", substr($bytes, 36, 5));
            $path = $this->temporaryFile(substr_replace($bytes, "\x01" . str_repeat("\xFF", 7), 40, 8));

            try {
                self::assertSame($expected, WebmAudioInspector::isAudioOnly($path));
            } finally {
                unlink($path);
            }
        }
    }

    #[DataProvider('uncertainContainers')]
    public function testUncertainMetadataCannotClaimAudioOnly(string $bytes): void
    {
        $path = $this->temporaryFile($bytes);

        try {
            self::assertFalse(WebmAudioInspector::isAudioOnly($path));
        } finally {
            unlink($path);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function uncertainContainers(): iterable
    {
        $audio = self::element('AE', self::audioFields());
        $video = self::element('AE', self::element('D7', "\x02") . self::element('73C5', "\x02") . self::element('83', "\x01") . self::element('86', 'V_VP9'));
        yield 'not EBML' => ['webm A_OPUS' . self::element('1654AE6B', $audio)];
        yield 'wrong document type' => [self::webm($audio, docType: 'matroska')];
        yield 'empty tracks' => [self::webm('')];
        yield 'audio followed by video' => [self::webm($audio . $video)];
        yield 'video followed by audio' => [self::webm($video . $audio)];
        yield 'unknown track type' => [self::webm(self::element('AE', str_replace("\x83\x81\x02", "\x83\x81\x03", self::audioFields())))];
        yield 'missing track type' => [self::webm(self::element('AE', str_replace("\x83\x81\x02", '', self::audioFields())))];
        yield 'duplicate track type' => [self::webm(self::element('AE', self::audioFields() . self::element('83', "\x01")))];
        yield 'video codec in audio entry' => [self::webm(self::element('AE', str_replace('A_OPUS', 'V_VP9', self::audioFields())))];
        yield 'video settings in audio entry' => [self::webm(self::element('AE', self::audioFields() . self::element('E0', '')))];
        yield 'unknown entry' => [self::webm($audio . self::element('A1', "\x83\x81\x01"))];
        yield 'unknown track field' => [self::webm(self::element('AE', self::audioFields() . self::element('A1', '')))];
        yield 'duplicate tracks' => [self::webm($audio, self::element('1654AE6B', $video))];
        yield 'unknown size tracks' => [self::document(hex2bin('1654AE6BFF') . $audio)];
        yield 'unknown size cluster' => [self::webm($audio, "\x1F\x43\xB6\x75\xFF")];
        yield 'truncated track entry' => [self::webm(hex2bin('AEFE') . self::audioFields())];
        yield 'truncated nested audio settings' => [self::webm(self::element('AE', self::audioFields() . hex2bin('E1829F')))];
        yield 'track markers in padding' => [self::document(self::element('EC', self::element('1654AE6B', $audio)))];
        yield 'invalid VINT' => [self::webm($audio, "\x00\x80")];
        yield 'truncated size VINT' => [self::webm($audio, "\xEC\x01\x00")];
        yield 'oversized element' => [self::webm($audio, "\xEC\x01\xFF\xFF\xFF\xFF\xFF\xFF\xFE")];
        yield 'unknown top-level element' => [self::webm($audio, self::element('1A111111', ''))];
        yield 'truncated segment' => [substr(self::webm($audio), 0, -1)];
        yield 'concatenated documents' => [self::webm($audio) . self::webm($video)];
        yield 'metadata byte budget' => [self::webm(self::element('AE', self::audioFields() . self::element('63A2', str_repeat('x', 1024 * 1024))))];
        yield 'element budget' => [self::webm($audio, str_repeat(self::element('EC', ''), 5000))];
        yield 'unknown root with video' => [self::webm($audio . $video, unknownSegment: true)];
        yield 'unknown root with concatenated document' => [self::webm($audio, unknownSegment: true) . self::webm($video)];
        yield 'unknown root with malformed tail' => [self::webm($audio, "\x00\x80", unknownSegment: true)];
        yield 'unknown root with duplicate tracks' => [self::webm($audio, self::element('1654AE6B', $video), unknownSegment: true)];
        yield 'unknown root with unknown tracks size' => [self::document(hex2bin('1654AE6BFF') . $audio, unknownSegment: true)];
        yield 'unknown root with unknown cluster size' => [self::webm($audio, "\x1F\x43\xB6\x75\xFF", unknownSegment: true)];
        yield 'unknown root element budget' => [self::webm($audio, str_repeat(self::element('EC', ''), 5000), unknownSegment: true)];
    }

    private function temporaryFile(string $bytes): string
    {
        $path = sys_get_temp_dir() . '/echo-webm-test-' . bin2hex(random_bytes(8));
        file_put_contents($path, $bytes);

        return $path;
    }

    private static function audioFields(): string
    {
        return self::element('D7', "\x01") . self::element('73C5', "\x01") . self::element('83', "\x02") . self::element('86', 'A_OPUS') . self::element('E1', '');
    }

    private static function webm(string $entries, string $tail = '', string $docType = 'webm', bool $unknownSegment = false): string
    {
        return self::document(self::element('1549A966', '') . self::element('1654AE6B', $entries) . self::element('1F43B675', '') . $tail, $docType, $unknownSegment);
    }

    private static function document(string $segment, string $docType = 'webm', bool $unknownSegment = false): string
    {
        $body = $unknownSegment ? "\x18\x53\x80\x67\xFF" . $segment : self::element('18538067', $segment);

        return self::element('1A45DFA3', self::element('4282', $docType)) . $body;
    }

    private static function element(string $id, string $payload): string
    {
        $length = strlen($payload);
        $width = 1;
        while ($length >= (1 << (7 * $width)) - 1) {
            ++$width;
        }
        $size = '';
        for ($index = $width - 1; $index >= 0; --$index) {
            $size .= chr(($length >> (8 * $index)) & 0xFF);
        }
        $size[0] = chr(ord($size[0]) | (1 << (8 - $width)));

        return hex2bin($id) . $size . $payload;
    }
}
