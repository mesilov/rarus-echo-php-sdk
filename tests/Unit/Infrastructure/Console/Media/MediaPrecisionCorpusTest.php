<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner;
use Rarus\Echo\Infrastructure\Console\Media\PreparationPolicy;

final class MediaPrecisionCorpusTest extends TestCase
{
    public function testCompactToolSourcePrecisionPolicyAndIntegerRoundTrips(): void
    {
        if (getenv('RARUS_ECHO_RUN_MEDIA_ACCEPTANCE') !== '1') {
            self::markTestSkipped('Requires the explicit final bundled media tool pair.');
        }
        $ffmpeg = getenv('RARUS_ECHO_FFMPEG_PATH');
        $ffprobe = getenv('RARUS_ECHO_FFPROBE_PATH');
        self::assertIsString($ffmpeg);
        self::assertIsString($ffprobe);
        $directory = sys_get_temp_dir() . '/echo-precision-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $runner = new MediaProcessRunner();

        try {
            foreach ([[16, 1, [1001, -1003, 12345, -23456]], [24, 1, [1000001, -1000003, 1234567, -2345678]], [32, 1, [100000001, -100000003]], [32, 3, [0.1234567, -0.7654321]]] as [$bits, $tag, $values]) {
                $pcm = '';
                foreach ($values as $value) {
                    $pcm .= $tag === 3 ? pack('g', $value) : substr(pack('V', $value), 0, intdiv($bits, 8));
                }
                $pcm = str_repeat($pcm, 256);
                $source = $directory . '/source.wav';
                $wave = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, $tag, 1, 48000, 48000 * intdiv($bits, 8), intdiv($bits, 8), $bits) . 'data' . pack('V', strlen($pcm)) . $pcm;
                file_put_contents($source, $wave);
                $probe = json_decode($runner->run([$ffprobe, '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $source], microtime(true) + 10), true, flags: JSON_THROW_ON_ERROR);
                $stream = $probe['streams'][0];
                $stream['start_time'] = 0;
                $stream['duration'] = strlen($pcm) / intdiv($bits, 8) / 48000;
                if ($bits === 32) {
                    try {
                        (new PreparationPolicy(true, []))->plan($stream, 0);
                    } catch (\RuntimeException $exception) {
                        self::assertStringContainsString('precision', $exception->getMessage());

                        continue;
                    }
                    self::fail('Compact-probed 32-bit source was accepted.');
                }
                $plan = (new PreparationPolicy(true, []))->plan($stream, 0);
                $prepared = $directory . '/prepared.flac';
                $runner->run(array_merge([$ffmpeg, '-v', 'error', '-i', $source], $plan->arguments, ['-y', $prepared]), microtime(true) + 10);
                $output = json_decode($runner->run([$ffprobe, '-v', 'error', '-show_streams', '-of', 'json', $prepared], microtime(true) + 10), true, flags: JSON_THROW_ON_ERROR);
                self::assertSame($bits, (int) $output['streams'][0]['bits_per_raw_sample']);
                $decoded = $runner->run([$ffmpeg, '-v', 'error', '-i', $prepared, '-c:a', 'pcm_s' . $bits . 'le', '-f', 'wav', 'pipe:1'], microtime(true) + 10);
                $data = strpos($decoded, 'data');
                self::assertNotFalse($data);
                self::assertSame($pcm, substr($decoded, $data + 8));
            }
        } finally {
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
