<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Infrastructure\Console\Media\MediaPreparer;
use Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner;

/** Explicit bundled-tool acceptance; never falls back to a host FFmpeg installation. */
final class MediaPreparerCorpusTest extends TestCase
{
    public function testBundledCoordinatorRoutesAndDecodedDelay(): void
    {
        if (getenv('RARUS_ECHO_RUN_MEDIA_ACCEPTANCE') !== '1') {
            self::markTestSkipped('Set RARUS_ECHO_RUN_MEDIA_ACCEPTANCE=1 and the absolute bundled tool pair.');
        }
        $root = dirname(__DIR__, 4) . '/Fixtures/Media/';
        $cases = [
            'video-aac.mp4' => 'm4a', 'video-opus.webm' => 'ogg',
            'video-ac3.mkv' => 'flac', 'video-flac.mkv' => 'flac',
            'two-tracks.mkv' => 'm4a', 'delayed.mp4' => 'flac',
            'audio-only.m4a' => null, 'audio-only.webm' => null, 'artwork.mp3' => null,
        ];
        foreach ($cases as $name => $extension) {
            $messages = [];
            $set = (new MediaPreparer())->prepare([$root . $name], new MediaOptions(), static function (string $message) use (&$messages): void {
                $messages[] = $message;
            });
            $path = $set->paths()[0];

            try {
                if ($extension === null) {
                    self::assertSame($root . $name, $path);
                    self::assertSame([], $messages);
                } else {
                    self::assertSame($extension, pathinfo($path, PATHINFO_EXTENSION));
                    self::assertFileExists($path);
                    if ($name === 'two-tracks.mkv') {
                        self::assertStringContainsString('audio_stream=2', $messages[0]);
                    }
                    if ($name === 'delayed.mp4') {
                        $binary = getenv('RARUS_ECHO_FFMPEG_PATH');
                        self::assertIsString($binary);
                        $wave = (new MediaProcessRunner())->run([$binary, '-v', 'error', '-i', $path, '-c:a', 'pcm_s16le', '-f', 'wav', 'pipe:1'], microtime(true) + 10);
                        $data = strpos($wave, 'data');
                        self::assertNotFalse($data);
                        $pcm = substr($wave, $data + 8);
                        $onset = null;
                        for ($offset = 0; $offset + 4 <= strlen($pcm); $offset += 4) {
                            $value = ord($pcm[$offset]) | (ord($pcm[$offset + 1]) << 8);
                            $sample = $value >= 32768 ? $value - 65536 : $value;
                            if (abs($sample) > 1000) {
                                $onset = $offset / 4 / 48000;

                                break;
                            }
                        }
                        self::assertNotNull($onset);
                        self::assertEqualsWithDelta(2.5, $onset, 0.05);
                    }
                }
            } finally {
                $set->cleanup();
            }
            if ($extension !== null) {
                self::assertFileDoesNotExist($path);
            }
        }
        foreach (['no-audio.mp4', 'corrupt.mp4'] as $name) {
            try {
                (new MediaPreparer())->prepare([$root . $name], new MediaOptions(), static function (string $message): void {
                });
                self::fail('Invalid media was accepted: ' . $name);
            } catch (\RuntimeException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
