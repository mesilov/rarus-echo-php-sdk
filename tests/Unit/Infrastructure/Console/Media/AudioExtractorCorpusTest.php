<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\ApplicationFactory;
use Rarus\Echo\Infrastructure\Console\Media\MediaProcessRunner;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FailingEchoClientFactory;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

final class AudioExtractorCorpusTest extends TestCase
{
    public function testLocalExtractionWithRealTools(): void
    {
        if (getenv('RARUS_ECHO_RUN_MEDIA_ACCEPTANCE') !== '1') {
            self::markTestSkipped('Set RARUS_ECHO_RUN_MEDIA_ACCEPTANCE=1 and the absolute bundled tool pair.');
        }
        $fixtures = dirname(__DIR__, 4) . '/Fixtures/Media/';
        $directory = sys_get_temp_dir() . '/echo-extraction-corpus-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $directory = (string) realpath($directory);
        $factory = new FailingEchoClientFactory();

        try {
            foreach ([
                ['video-aac.mp4', 'm4a', 'aac', null],
                ['video-opus.webm', 'ogg', 'opus', null],
                ['video-ac3.mkv', 'flac', 'flac', null],
                ['delayed.mp4', 'flac', 'flac', null],
                ['two-tracks.mkv', 'm4a', 'aac', 1],
            ] as [$name, $extension, $codec, $index]) {
                $source = $directory . '/видео $ literal ' . $name;
                copy($fixtures . $name, $source);
                $hash = hash_file('sha256', $source);
                $application = ApplicationFactory::create($factory);
                $application->setAutoExit(false);
                $tester = new ApplicationTester($application);
                $arguments = ['command' => 'extract-audio', 'file' => $source, '--config' => '/missing/config.json', '--json' => true];
                if ($index !== null) {
                    $arguments['--audio-stream'] = (string) $index;
                }
                self::assertSame(0, $tester->run($arguments, ['capture_stderr_separately' => true]), $tester->getErrorOutput());
                $path = $directory . DIRECTORY_SEPARATOR . pathinfo($source, PATHINFO_FILENAME) . '.' . $extension;
                self::assertSame(['output' => $path], json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR));
                self::assertSame($hash, hash_file('sha256', $source));
                $probe = getenv('RARUS_ECHO_FFPROBE_PATH');
                self::assertIsString($probe);
                $streams = json_decode((new MediaProcessRunner())->run([$probe, '-v', 'error', '-show_streams', '-of', 'json', $path], microtime(true) + 10), true, flags: JSON_THROW_ON_ERROR)['streams'];
                self::assertCount(1, $streams);
                self::assertSame('audio', $streams[0]['codec_type']);
                self::assertSame($codec, $streams[0]['codec_name']);
                if ($index !== null) {
                    self::assertStringContainsString('audio_stream=1', $tester->getErrorOutput());
                }
                $outputHash = hash_file('sha256', $path);
                self::assertSame(1, $tester->run($arguments, ['capture_stderr_separately' => true]));
                self::assertSame($outputHash, hash_file('sha256', $path));
            }
            foreach (['audio-only.m4a', 'audio-only.webm', 'artwork.mp3', 'no-audio.mp4', 'corrupt.mp4', 'missing.mp4'] as $name) {
                $application = ApplicationFactory::create($factory);
                $application->setAutoExit(false);
                $tester = new ApplicationTester($application);
                self::assertSame(1, $tester->run(['command' => 'extract-audio', 'file' => $fixtures . $name, '--json' => true], ['capture_stderr_separately' => true]));
                self::assertSame('', $tester->getDisplay());
                self::assertStringContainsString('Error:', $tester->getErrorOutput());
            }
            self::assertSame(0, $factory->calls);
            self::assertSame([], glob($directory . '/.rarus-echo-audio-*'));
        } finally {
            (new Filesystem())->remove($directory);
        }
    }
}
